<?php

/**
 * This file is part of the Translator
 * Copyright (c) 2018 Stanislav Janů (https://www.lweb.cz)
 */

declare(strict_types=1);

namespace JCode\Tests\Translator;

use JCode\Translator;
use JCode\TranslatorBadLanguageException;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\Connection;
use Nette\Database\ConnectionException;
use Nette\Database\Context;
use Nette\Database\Structure;
use Nette\Utils\DateTime;
use Tester\Assert;

require __DIR__.'/bootstrap.php';



// SetUp
$dsn      = 'mysql:host=127.0.0.1;dbname=test';
$user     = 'root';
$password = '';
$options  = [
	\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
];

$storage = new MemoryStorage();
try {
	$connection = new Connection($dsn, $user, $password, $options);
} catch (ConnectionException $exception) {
	$password   = 'root';
	$connection = new Connection($dsn, $user, $password, $options);
}

$structure = new Structure($connection, $storage);
$database  = new Context($connection, $structure);

$database->beginTransaction();
$database->query(
	'SET FOREIGN_KEY_CHECKS=0;'
	.'DROP TABLE `languages`;'
	.'DROP TABLE `translations`;'
	.'SET FOREIGN_KEY_CHECKS=1;'
);
$database->commit();

$database->beginTransaction();
$database->query(file_get_contents(__DIR__ . '/files/tables.sql'));
$database->commit();

$translator = new Translator($database, $storage);



// Testing
Assert::exception(function () use ($translator) {
	$translator->setSelectedLanguage('de');
}, TranslatorBadLanguageException::class);


$translator->setSelectedLanguage('cz');

$languages = $translator->getLanguages();

Assert::true(isset($languages['cz']));
Assert::true(isset($languages['en']));
Assert::false(isset($languages['de']));

Assert::same('Non exists', $translator->translate('Non exists'));
Assert::same('test', $translator->translate('app.test'));
Assert::same('testy', $translator->translate('app.test', 2));
Assert::same('testů', $translator->translate('app.test', 5));

Assert::same('Non exists', $translator->translate('Non exists', 'test'));
Assert::same('prasátko', $translator->translate('app.test', 'test'));
Assert::same('prasátka', $translator->translate('app.test', 'test', 2));
Assert::same('prasátek', $translator->translate('app.test', 'test', 5));

$translator->setSelectedLanguage('en');

Assert::same('Non exists', $translator->translate('Non exists'));
Assert::same('app.test', $translator->translate('app.test'));
Assert::same('app.test', $translator->translate('app.test', 2));
Assert::same('app.test', $translator->translate('app.test', 5));

Assert::same('17. June 1991  3:33:12', $translator->translateDateTime(DateTime::from('1991-06-17 03:33:12'), '%e. %B %Y %k:%M:%S'));
Assert::same('17. June 1991  3:33:12', $translator->translateDateTime(677122392, '%e. %B %Y %k:%M:%S'));



// Clean
$database->beginTransaction();
$database->query(
	'SET FOREIGN_KEY_CHECKS=0;'
	.'DROP TABLE `languages`;'
	.'DROP TABLE `translations`;'
	.'SET FOREIGN_KEY_CHECKS=1;'
);
$database->commit();
