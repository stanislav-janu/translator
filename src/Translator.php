<?php

/**
 * This file is part of the Translator
 * Copyright (c) 2018 Stanislav Janů (https://www.lweb.cz)
 */

declare(strict_types=1);

namespace JCode;

use DateTimeInterface;
use Nette;
use Nette\Utils\Strings;
use function Safe\sprintf;


/**
 * Class Translator
 * @package JCode
 */
class Translator implements Nette\Localization\Translator
{
	public const LANGUAGES_TABLE_NAME = 'languages';
	public const TRANSLATIONS_TABLE_NAME = 'translations';

	public Nette\Database\Explorer $database;

	public Nette\Caching\Cache $cache;

	/** @var array<string> */
	private array $languages;

	/** @var array<array> */
	private array $translations = [];

	private string $defaultLanguage = 'cz';

	private string $selectedLanguage = 'cz';


	/**
	 * Translator constructor.
	 *
	 * @param Nette\Database\Explorer $database
	 * @param Nette\Caching\Storage   $storage
	 */
	public function __construct(Nette\Database\Explorer $database, Nette\Caching\Storage $storage)
	{
		$this->database = $database;
		$this->cache = new Nette\Caching\Cache($storage, 'JCode-translator');

		$this->languages = $this->cache->load('translator-languages', function (&$dependencies) use ($database): array {
			$dependencies = [
				Nette\Caching\Cache::EXPIRE => '60 minutes',
			];

			return $database->table(self::LANGUAGES_TABLE_NAME)
				->order('order')
				->fetchPairs('code', 'name');
		});
	}


	/**
	 * @param int|DateTimeInterface $date
	 * @param string                $format
	 *
	 * @return string
	 */
	public function translateDateTime($date, string $format = '%e. %B %Y'): string
	{
		if ($date instanceof DateTimeInterface) {
			$timestamp = $date->getTimestamp();
		} else {
			$timestamp = $date;
		}

		return (string) strftime($format, $timestamp);
	}


	/**
	 * @param mixed $message
	 * @param mixed ...$parameters
	 *
	 * @return string
	 */
	public function translate($message, ...$parameters): string
	{
		if (!is_string($message)) {
			throw new \TypeError(sprintf('Parameter $message must be string, %s given.', gettype($message)));
		}

		$namespace = null;
		$count = 1;

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
			if (
				$translation['namespace'] === $namespace
				&& $translation['original'] === $message
				&& $translation['count'] === $count
			) {
				if (Strings::trim($translation['translation']) === '' || $translation['translation'] === null) {
					return $message;
				}

				return $translation['translation'];
			}
		}

		$this->writeForTranslate($message, $namespace, $count);

		return $message;
	}


	private static function getCount(int $number = null): int
	{
		if ($number === null) {
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


	public function writeForTranslate(string $message, ?string $namespace, int $count = 1): void
	{
		if (
			Strings::trim($message) !== ''
			&& (
				Strings::match($message, '/^::[a-zA-Z0-9-._]*$/s') !== null
				|| $this->selectedLanguage !== $this->defaultLanguage
			)
		) {
			$find = $this->database->table(self::TRANSLATIONS_TABLE_NAME)
				->where('language', $this->selectedLanguage)
				->where('original', $message)
				->where('namespace', $namespace)
				->where('count', (string) $count)
				->count();

			if ($find === 0) {
				$this->database->table(self::TRANSLATIONS_TABLE_NAME)
					->insert([
						'language' => $this->selectedLanguage,
						'original' => $message,
						'namespace' => $namespace,
						'count' => (string) $count,
						'translation' => '',
					]);
				$this->translations[] = [
					'original' => $message,
					'namespace' => $namespace,
					'count' => (string) $count,
					'translation' => '',
				];

				if ($count > 1) {
					$this->writeForTranslate($message, $namespace, 1);
					$this->writeForTranslate($message, $namespace, $count === 2 ? 5 : 2);
				}
			}
		}
	}


	/**
	 * @param string $selectedLanguage
	 *
	 * @return Translator
	 * @throws TranslatorBadLanguageException
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


	public static function getLocale(string $lang): string
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


	private function loadTranslations(): void
	{
		$database = $this->database;
		$selectedLanguage = $this->selectedLanguage;

		$this->translations = $this->cache->load('translations-' . $selectedLanguage, function (&$dependencies) use ($database, $selectedLanguage): array {
			$dependencies = [
				Nette\Caching\Cache::EXPIRE => '60 minutes',
			];
			$translations = [];
			foreach ($database->table(self::TRANSLATIONS_TABLE_NAME)
				->where('language', $selectedLanguage)
				->fetchAll() as $item) {
				$translations[] = [
					'original' => $item->original,
					'namespace' => $item->namespace,
					'translation' => $item->translation,
					'count' => (int) $item->count,
				];
			}

			return $translations;
		});
	}


	/**
	 * @param string $defaultLanguage
	 *
	 * @return Translator
	 * @throws TranslatorBadLanguageException
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
	 * @return array<string>
	 */
	public function getLanguages(): array
	{
		return $this->languages;
	}


	/**
	 * @return string
	 */
	public function getSelectedLanguage(): string
	{
		return $this->selectedLanguage;
	}
}
