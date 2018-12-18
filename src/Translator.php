<?php

/**
 * This file is part of the Translator
 * Copyright (c) 2018 Stanislav Janů (https://www.lweb.cz)
 */

declare(strict_types=1);

namespace JCode;

use Nette;
use Nette\Utils\Strings;


/**
 * Class Translator
 * @package JCode
 */
class Translator implements Nette\Localization\ITranslator
{
	const
		LANGUAGES_TABLE_NAME = 'languages',
		TRANSLATIONS_TABLE_NAME = 'translations';

	/** @var Nette\Database\Context */
	public $database;

	/** @var Nette\Caching\Cache */
	public $cache;

	/** @var array */
	private $languages = [];

	/** @var array */
	private $translations = [];

	/** @var string */
	private $defaultLanguage = 'cz';

	/** @var string */
	private $selectedLanguage = 'cz';

	/**
	 * Translator constructor.
	 *
	 * @param \Nette\Database\Context $database
	 * @param \Nette\Caching\IStorage $storage
	 */
	public function __construct(Nette\Database\Context $database, Nette\Caching\IStorage $storage)
	{
		$this->database = $database;
		$this->cache    = new Nette\Caching\Cache($storage, 'JCode-translator');

		$this->languages = $this->cache->load('translator-languages', function (&$dependencies) use ($database) {
			$dependencies = [
				Nette\Caching\Cache::EXPIRE => '60 minutes',
			];

			return $database->table(self::LANGUAGES_TABLE_NAME)->order('order')->fetchPairs('code', 'name');
		});
	}

	/**
	 * @param int|\DateTimeInterface $date
	 * @param string                 $format
	 *
	 * @return string
	 */
	public function translateDateTime($date, string $format = '%e. %B %Y'): string
	{
		if ($date instanceof \DateTimeInterface) {
			$timestamp = $date->getTimestamp();
		} else {
			$timestamp = $date;
		}

		return strftime($format, $timestamp);
	}

	/**
	 * @param string             $message
	 * @param array<int, string> $parameters
	 *
	 * @return string
	 */
	public function translate($message, ...$parameters): string
	{
		$namespace = NULL;
		$count     = 1;

		if (isset($parameters[0])) {
			if (is_int($parameters[0])) {
				$count = $parameters[0];
			} elseif (is_string($parameters[0])) {
				$namespace = $parameters[0];
				if (isset($parameters[1]) && is_int($parameters[1])) {
					$count = $parameters[1];
				}
			}
		}

		$count = self::getCount($count);

		// Mark for stopping translating text
		if (Strings::substring($message, 0, 1) === '!') {
			return Strings::substring($message, 1, Strings::length($message) - 1);
		}

		foreach ($this->translations as $translation) {
			if ($translation['namespace'] === $namespace && $translation['original'] === $message && $translation['count'] === $count) {
				if (empty($translation['translation'])) {
					return $message;
				}

				return $translation['translation'];
			}
		}

		$this->writeForTranslate($message, $namespace, $count);

		return $message;
	}

	/**
	 * @param int|null $number
	 *
	 * @return int
	 */
	private static function getCount(int $number = NULL): int
	{
		if ($number === NULL) {
			return 1;
		}
		if ($number >= 5 || $number === 0 || $number <= -5) {
			return 5;
		}
		if ($number >= 2 || $number <= -2) {
			return 2;
		}

		return 1;
	}

	/**
	 * @param string      $message
	 * @param string|null $namespace
	 * @param int         $count
	 */
	public function writeForTranslate(string $message, ?string $namespace, int $count = 1)
	{
		if (
			!empty($message) &&
			(
				preg_match('/^::[a-zA-Z1-9-._]*$/s', $message) === 1 ||
				$this->selectedLanguage !== $this->defaultLanguage
			)
		) {
			$find = $this->database->table(self::TRANSLATIONS_TABLE_NAME)
				->where('language', $this->selectedLanguage)
				->where('original', $message)
				->where('namespace', $namespace)
				->where('count', (string) $count)
				->count();

			if ($find === 0) {
				$this->database->table(self::TRANSLATIONS_TABLE_NAME)->insert([
					'language'    => $this->selectedLanguage,
					'original'    => $message,
					'namespace'   => $namespace,
					'count'       => (string) $count,
					'translation' => '',
				]);
				$this->translations[] = [
					'original'    => $message,
					'namespace'   => $namespace,
					'count'       => (string) $count,
					'translation' => '',
				];

				if ($count > 1) {
					$this->writeForTranslate($message, $namespace, 1);
					$this->writeForTranslate($message, $namespace, $count === 2 ? 5 : 2);
				};
			}
		}
	}

	/**
	 * @param string $selectedLanguage
	 *
	 * @return \JCode\Translator
	 * @throws \JCode\TranslatorBadLanguageException
	 */
	public function setSelectedLanguage(string $selectedLanguage): self
	{
		if (!isset($this->languages[$selectedLanguage])) {
			throw new TranslatorBadLanguageException;
		}

		setlocale(LC_ALL, self::getLocale($selectedLanguage) . '.UTF-8');
		$this->selectedLanguage = $selectedLanguage;
		$this->loadTranslations();

		return $this;
	}

	/**
	 * @param string $lang
	 *
	 * @return string
	 */
	static function getLocale(string $lang): string
	{
		$locales = [
			'cz' => 'cs_CZ',
			'en' => 'en_US',
			'de' => 'de_DE',
			'ru' => 'ru_RU',
		];

		if (isset($locales[$lang])) {
			return $locales[$lang];
		}

		return 'en_US';
	}

	private function loadTranslations()
	{
		$database         = $this->database;
		$selectedLanguage = $this->selectedLanguage;

		$this->translations = $this->cache->load('translations-' . $selectedLanguage, function (&$dependencies) use ($database, $selectedLanguage) {
			$dependencies = [
				Nette\Caching\Cache::EXPIRE => '60 minutes',
			];
			$translations = [];
			foreach ($database->table(self::TRANSLATIONS_TABLE_NAME)->where('language', $selectedLanguage)->fetchAll() as $item) {
				$translations[] = [
					'original'    => $item->original,
					'namespace'   => $item->namespace,
					'translation' => $item->translation,
					'count'       => (int) $item->count,
				];
			}

			return $translations;
		});
	}

	/**
	 * @param string $defaultLanguage
	 *
	 * @return \JCode\Translator
	 * @throws \JCode\TranslatorBadLanguageException
	 */
	public function setDefaultLanguage(string $defaultLanguage): self
	{
		if (!isset($this->languages[$defaultLanguage])) {
			throw new TranslatorBadLanguageException;
		}

		$this->defaultLanguage = $defaultLanguage;

		return $this;
	}

	/**
	 * @return array
	 */
	public function getLanguages(): array
	{
		return $this->languages;
	}

}
