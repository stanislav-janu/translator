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

Assert::same((string) $translator->translate('Non exists'), 'Non exists');
Assert::same((string) $translator->translate('app.test'), 'test');
Assert::same((string) $translator->translate('app.test', 2), 'testy');
Assert::same((string) $translator->translate('app.test', 5), 'testů');

$translator->setSelectedLanguage('en');

Assert::same((string) $translator->translate('Non exists'), 'Non exists');
Assert::same((string) $translator->translate('app.test'), 'app.test');
Assert::same((string) $translator->translate('app.test', 2), 'app.test');
Assert::same((string) $translator->translate('app.test', 5), 'app.test');

Assert::same((string) $translator->translate(DateTime::from('1991-06-17 03:33:12'), '%e. %B %Y %k:%M:%S'), '17. June 1991  3:33:12');



// Clean
$database->beginTransaction();
$database->query(
	'SET FOREIGN_KEY_CHECKS=0;'
	.'DROP TABLE `languages`;'
	.'DROP TABLE `translations`;'
	.'SET FOREIGN_KEY_CHECKS=1;'
);
$database->commit();
