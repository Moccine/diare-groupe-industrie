<?php

namespace App\Tests\Controller;

use App\Command\TestMailCommand;
use App\Controller\Admin\Crud\JobOfferCrudController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AdminLinkFactory;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PasswordResetTest extends WebTestCase
{
    use MailerAssertionsTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite est requis pour la réinitialisation du mot de passe.');
        }

        $databaseUrl = 'sqlite:///'.dirname(__DIR__, 2).'/var/password_reset_phpunit.db';
        putenv('APP_ENV=test');
        putenv('DATABASE_URL='.$databaseUrl);
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $databaseUrl;

        $this->client = static::createClient();
        $manager = $this->manager();
        $params = $manager->getConnection()->getParams();
        if (($params['driver'] ?? '') !== 'pdo_sqlite') {
            self::markTestSkipped('Le test de réinitialisation refuse de s’exécuter hors SQLite.');
        }

        $tool = new SchemaTool($manager);
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $this->clearRateLimiter();
    }

    public function testLoginPageLinksToForgotPassword(): void
    {
        $this->client->request('GET', '/administration/connexion');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
        self::assertSelectorExists('a[href="/administration/mot-de-passe-oublie"]');
        self::assertSelectorExists('label .form-required');
    }

    public function testRequiredBackOfficeFieldsShowARedStar(): void
    {
        $admin = $this->createAdmin('AncienMotDePasse1!');
        $this->client->loginUser($admin, 'admin');
        $links = static::getContainer()->get(AdminLinkFactory::class);
        self::assertInstanceOf(AdminLinkFactory::class, $links);
        $url = $links->to(JobOfferCrudController::class, Action::NEW);
        $parts = parse_url($url);
        self::assertIsArray($parts);

        $this->client->request('GET', ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : ''));

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertMatchesRegularExpression(
            '/Intitulé du poste\s*<span class="form-required" aria-hidden="true">\*<\/span>/',
            $content,
        );
        self::assertDoesNotMatchRegularExpression(
            '/Publiée\s*<span class="form-required"/',
            $content,
        );
    }

    public function testUnknownEmailShowsTheSameConfirmationAndSendsNothing(): void
    {
        $this->client->request('GET', '/administration/mot-de-passe-oublie');
        $this->client->submitForm('Envoyer le lien', [
            'forgot_password_form[email]' => 'inconnu@example.com',
        ]);

        self::assertResponseRedirects('/administration/mot-de-passe-oublie/envoye');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Vérifiez votre boîte mail');
        self::assertSelectorTextContains('body', 'l’email de réinitialisation vient d’être envoyé');
        self::assertEmailCount(0);
    }

    public function testKnownEmailSendsAResetLinkAndPasswordCanBeChanged(): void
    {
        $this->createAdmin('AncienMotDePasse1!');

        $this->client->request('GET', '/administration/mot-de-passe-oublie');
        $this->client->submitForm('Envoyer le lien', [
            'forgot_password_form[email]' => 'Admin@Diare.local',
        ]);

        self::assertResponseRedirects('/administration/mot-de-passe-oublie/envoye');
        self::assertEmailCount(1);

        $token = $this->resetTokenFromMail();
        $user = $this->users()->findOneByEmail('admin@diare.local');
        self::assertInstanceOf(User::class, $user);
        self::assertSame(hash('sha256', $token), $user->getResetToken());
        self::assertNotSame($token, $user->getResetToken());

        $this->client->request('GET', '/administration/reinitialisation/'.$token);
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Enregistrer le mot de passe', [
            'reset_password_form[plainPassword][first]' => 'NouveauMotDePasse1!',
            'reset_password_form[plainPassword][second]' => 'NouveauMotDePasse1!',
        ]);

        self::assertResponseRedirects('/administration/connexion');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Votre mot de passe a été modifié');

        $this->manager()->clear();
        $updated = $this->users()->findOneByEmail('admin@diare.local');
        self::assertInstanceOf(User::class, $updated);
        self::assertNull($updated->getResetToken());
        self::assertTrue($this->hasher()->isPasswordValid($updated, 'NouveauMotDePasse1!'));
        self::assertFalse($this->hasher()->isPasswordValid($updated, 'AncienMotDePasse1!'));
    }

    public function testWeakPasswordIsRejectedAndTokenStaysValid(): void
    {
        $this->createAdmin('AncienMotDePasse1!');
        $this->client->request('GET', '/administration/mot-de-passe-oublie');
        $this->client->submitForm('Envoyer le lien', [
            'forgot_password_form[email]' => 'admin@diare.local',
        ]);
        $token = $this->resetTokenFromMail();

        $this->client->request('GET', '/administration/reinitialisation/'.$token);
        $this->client->submitForm('Enregistrer le mot de passe', [
            'reset_password_form[plainPassword][first]' => 'court',
            'reset_password_form[plainPassword][second]' => 'court',
        ]);

        self::assertSelectorTextContains('body', 'au moins 8 caractères');
        $user = $this->users()->findOneByEmail('admin@diare.local');
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isResetTokenValid());
        self::assertTrue($this->hasher()->isPasswordValid($user, 'AncienMotDePasse1!'));
    }

    public function testExpiredTokenIsRejected(): void
    {
        $this->createAdmin('AncienMotDePasse1!');
        $this->client->request('GET', '/administration/mot-de-passe-oublie');
        $this->client->submitForm('Envoyer le lien', [
            'forgot_password_form[email]' => 'admin@diare.local',
        ]);
        $token = $this->resetTokenFromMail();

        $user = $this->users()->findOneByEmail('admin@diare.local');
        self::assertInstanceOf(User::class, $user);
        $user->setResetTokenExpiresAt(new \DateTimeImmutable('-1 minute'));
        $this->manager()->flush();

        $this->client->request('GET', '/administration/reinitialisation/'.$token);

        self::assertResponseRedirects('/administration/mot-de-passe-oublie');
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'invalide ou a expiré');
    }

    public function testHoneypotDoesNotSendEmail(): void
    {
        $this->createAdmin('AncienMotDePasse1!');
        $this->client->request('GET', '/administration/mot-de-passe-oublie');
        $this->client->submitForm('Envoyer le lien', [
            'forgot_password_form[email]' => 'admin@diare.local',
            'forgot_password_form[website]' => 'https://spam.example',
        ]);

        self::assertResponseRedirects('/administration/mot-de-passe-oublie/envoye');
        self::assertEmailCount(0);
        $user = $this->users()->findOneByEmail('admin@diare.local');
        self::assertInstanceOf(User::class, $user);
        self::assertNull($user->getResetToken());
    }

    public function testMailCommandSendsATestMessage(): void
    {
        $command = static::getContainer()->get(TestMailCommand::class);
        self::assertInstanceOf(TestMailCommand::class, $command);
        $tester = new CommandTester($command);

        self::assertSame(Command::SUCCESS, $tester->execute(['to' => 'qa@example.com']));
        self::assertEmailCount(1);
        $message = $this->getMailerMessage();
        self::assertNotNull($message);
        self::assertEmailAddressContains($message, 'To', 'qa@example.com');
        self::assertEmailAddressContains($message, 'From', 'noreply@diaregroupe.local');
        self::assertEmailHtmlBodyContains($message, 'email de test');
        self::assertEmailHtmlBodyContains($message, 'Diaré Groupe Industrie');

        self::assertSame(Command::FAILURE, $tester->execute(['to' => 'pas-un-email']));
    }

    private function createAdmin(string $password): User
    {
        $user = (new User())
            ->setEmail('admin@diare.local')
            ->setFullName('Awa Diop')
            ->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->hasher()->hashPassword($user, $password));
        $this->manager()->persist($user);
        $this->manager()->flush();

        return $user;
    }

    private function resetTokenFromMail(): string
    {
        $message = $this->getMailerMessage();
        self::assertNotNull($message);
        $html = (string) $message->getHtmlBody();
        self::assertMatchesRegularExpression('#/administration/reinitialisation/([a-f0-9]{64})#', $html);
        preg_match('#/administration/reinitialisation/([a-f0-9]{64})#', $html, $matches);

        return $matches[1];
    }

    private function hasher(): UserPasswordHasherInterface
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertInstanceOf(UserPasswordHasherInterface::class, $hasher);

        return $hasher;
    }

    private function users(): UserRepository
    {
        $repository = $this->manager()->getRepository(User::class);
        self::assertInstanceOf(UserRepository::class, $repository);

        return $repository;
    }

    private function clearRateLimiter(): void
    {
        $cache = static::getContainer()->get('cache.rate_limiter');
        if ($cache instanceof CacheItemPoolInterface) {
            $cache->clear();
        }
    }

    private function manager(): EntityManagerInterface
    {
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $manager);

        return $manager;
    }
}
