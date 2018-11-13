<?php declare(strict_types=1);

namespace JCode\Tests\Translator;

use JCode\Translator;
use Nette\Caching\Storages\MemoryStorage;
use Nette\Database\Connection;
use Nette\Database\ConnectionException;
use Nette\Database\Context;
use Nette\Database\Structure;
use PHPUnit\Framework\TestCase;

class TranslatorTest extends TestCase
{
	/** @var Context */
	private $database;

	/** @var Translator */
	private $translator;

	protected function setUp()
	{
		$dsn = 'mysql:host=127.0.0.1;dbname=test';
		$user = 'root';
		$password = '';
		$options = [
			\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
		];
		$storage = new MemoryStorage();
		try {
			$connection = new Connection($dsn, $user, $password, $options);
		}
		catch (ConnectionException $exception)
		{
			$password = 'root';
			$connection = new Connection($dsn, $user, $password, $options);
		}
		$structure = new Structure($connection, $storage);
		$context = new Context($connection, $structure);

		$context->beginTransaction();
		$context->query(file_get_contents(__DIR__.'/files/tables.sql'));
		$context->commit();

		$this->database = $context;
		$this->translator = new Translator($this->database, new MemoryStorage());
	}

	/**
	 * @expectedException \JCode\TranslatorBadLanguageException
	 */
	public function testSelectException()
	{
		$this->translator->setSelectedLanguage('de');
	}

	/**
	 * @expectedException \JCode\TranslatorBadLanguageException
	 */
	public function testDefaultException()
	{
		$this->translator->setDefaultLanguage('de');
	}

	public function testMain()
	{
		$this->translator->setDefaultLanguage('cz');
		$this->translator->setSelectedLanguage('cz');

		$this->assertArrayHasKey('cz', $this->translator->getLanguages());
		$this->assertArrayHasKey('en', $this->translator->getLanguages());
		$this->assertArrayNotHasKey('de', $this->translator->getLanguages());

		$this->assertSame('Non exists', $this->translator->translate('Non exists'));
		$this->assertSame('test', (string) $this->translator->translate('app.test'));
		$this->assertSame('testy', (string) $this->translator->translate('app.test', 2));
		$this->assertSame('testů', (string) $this->translator->translate('app.test', 5));

		$this->assertSame('::message.test', $this->translator->translate('::message.test'));
		$this->assertSame(1, $this->database->table(Translator::TRANSLATIONS_TABLE_NAME)->where('language', 'cz')->where('original', '::message.test')->count());

		$this->assertSame('::message_test', $this->translator->translate('::message_test'));
		$this->assertSame(1, $this->database->table(Translator::TRANSLATIONS_TABLE_NAME)->where('language', 'cz')->where('original', '::message_test')->count());

		$this->assertSame('::message-test', $this->translator->translate('::message-test'));
		$this->assertSame(1, $this->database->table(Translator::TRANSLATIONS_TABLE_NAME)->where('language', 'cz')->where('original', '::message-test')->count());

		$this->assertSame('::message test', $this->translator->translate('::message test'));
		$this->assertSame(0, $this->database->table(Translator::TRANSLATIONS_TABLE_NAME)->where('language', 'cz')->where('original', '::message test')->count());

		$this->assertSame('message test', $this->translator->translate('message test'));
		$this->assertSame(0, $this->database->table(Translator::TRANSLATIONS_TABLE_NAME)->where('language', 'cz')->where('original', 'message test')->count());

		$this->translator->setSelectedLanguage('en');

		$this->assertSame('Non exists', $this->translator->translate('Non exists'));
		$this->assertSame('app.test', (string) $this->translator->translate('app.test'));
		$this->assertSame('app.test', (string) $this->translator->translate('app.test', 2));
		$this->assertSame('app.test', (string) $this->translator->translate('app.test', 5));

		$this->assertSame('::message.test', $this->translator->translate('::message.test'));
		$this->assertSame(1, $this->database->table(Translator::TRANSLATIONS_TABLE_NAME)->where('language', 'en')->where('original', '::message.test')->count());

		$this->assertSame('::message_test', $this->translator->translate('::message_test'));
		$this->assertSame(1, $this->database->table(Translator::TRANSLATIONS_TABLE_NAME)->where('language', 'en')->where('original', '::message_test')->count());

		$this->assertSame('::message-test', $this->translator->translate('::message-test'));
		$this->assertSame(1, $this->database->table(Translator::TRANSLATIONS_TABLE_NAME)->where('language', 'en')->where('original', '::message-test')->count());

		$this->assertSame('::message test', $this->translator->translate('::message test'));
		$this->assertSame(1, $this->database->table(Translator::TRANSLATIONS_TABLE_NAME)->where('language', 'en')->where('original', '::message test')->count());

		$this->assertSame('message test', $this->translator->translate('message test'));
		$this->assertSame(1, $this->database->table(Translator::TRANSLATIONS_TABLE_NAME)->where('language', 'en')->where('original', 'message test')->count());
	}

	public function tearDown()
	{
		$this->database->beginTransaction();
		$this->database->query(
			'SET FOREIGN_KEY_CHECKS=0;'
			.'DROP TABLE `'.Translator::LANGUAGES_TABLE_NAME.'`;'
			.'DROP TABLE `'.Translator::TRANSLATIONS_TABLE_NAME.'`;'
			.'SET FOREIGN_KEY_CHECKS=1;'
		);
		$this->database->commit();
	}

}
