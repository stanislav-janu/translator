<?php declare(strict_types=1);

namespace JCode;

use Nette;
use Nette\Utils\Strings;

/**
 * Class Translator
 * @package JCode
 */
class Translator implements Nette\Localization\ITranslator
{
	/** @var Nette\Database\Context */
	public $database;

	/** @var Nette\Caching\Cache */
	public $cache;

	/** @var array */
	private $languages = [];

	/** @var array */
	private $translations = [];

	/** @var string */
	private $selected_language;

	const
		LANGUAGES_TABLE_NAME = 'languages',
		TRANSLATIONS_TABLE_NAME = 'translations';

	/**
	 * Translator constructor.
	 *
	 * @param \Nette\Database\Context $database
	 * @param \Nette\Caching\IStorage $storage
	 */
	public function __construct(Nette\Database\Context $database, Nette\Caching\IStorage $storage)
	{
		$this->database = $database;
		$this->cache = new Nette\Caching\Cache($storage, 'JCode-translator');

		$this->languages = $this->cache->load('translator-languages', function (& $dependencies) use ($database)
		{
			$dependencies = [
				Nette\Caching\Cache::EXPIRE => '60 minutes'
			];
			return $database->table(self::LANGUAGES_TABLE_NAME)->order('order')->fetchPairs('code', 'name');
		});
	}

	/**
	 * @param mixed $message
	 * @param mixed|null $count
	 *
	 * @return \Nette\Utils\Html|string
	 */
	public function translate($message, $count = null)
	{
		if(($message instanceof Nette\Utils\DateTime || $message instanceof \DateTime || $message instanceof DateTime) && is_string($count))
		{
			return self::translateDateTime(DateTime::from($message), (string) $count);
		}
		elseif(is_string($message))
		{
			$count = self::getCount(is_null($count) ? null : intval($count));

			if(Strings::substring($message, 0, 1) === '!') // Mark stopping translating text
				return Strings::substring($message, 1, Strings::length($message)-1);

			foreach($this->translations as $translation)
			{
				if($translation['original'] === $message && $translation['count'] === $count)
				{
					if(empty($translation['translation']))
						return Nette\Utils\Html::el()->setHtml($message);

					return Nette\Utils\Html::el()->setHtml($translation['translation']);
				}
			}

			$this->writeForTranslate($message, $count);
		}

		return $message;
	}

	/**
	 * @param string $selected_language
	 *
	 * @throws \JCode\TranslatorBadLanguageException
	 */
	public function setSelectedLanguage(string $selected_language)
	{
		if(!isset($this->languages[$selected_language]))
			throw new TranslatorBadLanguageException;

		setlocale(LC_ALL, self::getLocale($selected_language).'.UTF-8');
		$this->selected_language = $selected_language;
		$this->loadTranslations();
	}

	private function loadTranslations()
	{
		$database = $this->database;
		$selected_language = $this->selected_language;

		$this->translations = $this->cache->load('translations-'.$selected_language, function (&$dependencies) use ($database, $selected_language)
		{
			$dependencies = [
				Nette\Caching\Cache::EXPIRE => '60 minutes',
			];
			$translations = [];
			foreach ($database->table(self::TRANSLATIONS_TABLE_NAME)->where('language', $selected_language)->fetchAll() as $item)
			{
				$translations[] = [
					"original" => $item->original,
					"translation" => $item->translation,
					"count" => (int) $item->count,
				];
			}
			return $translations;
		});
	}

	/**
	 * @param string $message
	 * @param int    $count
	 */
	public function writeForTranslate(string $message, int $count = 1)
	{
		$count = (string) $count;
		if(
			($this->selected_language != 'cz' && !empty($message)) ||
			($this->selected_language == 'cz' && !empty($message) && (preg_match('/^[A-Z_]*$/s', $message) === 1 || (int) $count > 1))
		) {
			$c = $this->database->table(self::TRANSLATIONS_TABLE_NAME)
				->where('language', $this->selected_language)
				->where('original', $message)
				->where('count', $count)
				->count();

			if($c === 0)
			{
				$this->database->table(self::TRANSLATIONS_TABLE_NAME)->insert([
					"language" => $this->selected_language,
					"original" => $message,
					"count" => $count,
					"translation" => "",
				]);
				$this->translations[] = [
					"original" => $message,
					"count" => $count,
					"translation" => "",
				];
			}
		}
	}

	/**
	 * @return array
	 */
	public function getLanguages() : array
	{
		return $this->languages;
	}

	/**
	 * @param string $lang
	 *
	 * @return string
	 */
	static function getLocale(string $lang) : string
	{
		$locales = [
			'cz' => 'cs_CZ',
			'en' => 'en_US',
			'de' => 'de_DE',
			'ru' => 'ru_RU',
		];

		if(isset($locales[$lang]))
			return $locales[$lang];

		return 'en_US';
	}

	/**
	 * @param DateTime  $date
	 * @param string    $format
	 *
	 * @return string
	 */
	public static function translateDateTime(DateTime $date, string $format = '%e. %B %Y') : string
	{
		return strftime($format, $date->getTimestamp());
	}

	/**
	 * @param int|null $number
	 *
	 * @return int
	 */
	private static function getCount(int $number = null) : int
	{
		if($number === null)
			return 1;
		if($number >= 5 || $number == 0 || $number <= -5)
			return 5;
		if($number >= 2 || $number <= -2)
			return 2;
		return 1;
	}

}
