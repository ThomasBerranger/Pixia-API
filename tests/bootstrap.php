<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Crée la BDD de test au démarrage de PHPUnit
passthru('php bin/console doctrine:database:create --if-not-exists --env=test');
passthru('php bin/console doctrine:schema:drop --force --env=test');
passthru('php bin/console doctrine:schema:create --env=test');

// Charge les fixtures TestFixtures
$kernel = new \App\Kernel('test', true);
$kernel->boot();

$container = $kernel->getContainer();
$entityManager = $container->get('doctrine')->getManager();
$passwordHasher = $container->get('security.user_password_hasher');

$loader = new \Doctrine\Common\DataFixtures\Loader();
$loader->addFixture(new \App\DataFixtures\TestFixtures($passwordHasher));

$executor = new \Doctrine\Common\DataFixtures\Executor\ORMExecutor($entityManager);
$executor->execute($loader->getFixtures(), true);

$kernel->shutdown();
