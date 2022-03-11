<?php

declare(strict_types=1);

use Rector\Core\ValueObject\PhpVersion;
use Rector\Core\Configuration\Option;
use Rector\Set\ValueObject\SetList;
use Rector\Nette\Set\NetteSetList;
use Rector\TypeDeclaration\Rector\FunctionLike\ReturnTypeDeclarationRector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;


return static function (ContainerConfigurator $containerConfigurator): void {
	// get parameters
	$parameters = $containerConfigurator->parameters();

	$parameters->set(Option::PATHS, [
		__DIR__ . '/src',
		__DIR__ . '/tests',
	]);

	$parameters->set(Option::AUTO_IMPORT_NAMES, true);
	$parameters->set(Option::CACHE_DIR, __DIR__ . '/temp/rector');
	$parameters->set(Option::PARALLEL, true);

	// Define what rule sets will be applied
	$containerConfigurator->import(SetList::PHP_80);
	$containerConfigurator->import(SetList::CODE_QUALITY);
	$containerConfigurator->import(NetteSetList::NETTE_31);
	$containerConfigurator->import(NetteSetList::NETTE_REMOVE_INJECT);
	$containerConfigurator->import(NetteSetList::NETTE_CODE_QUALITY);
	$containerConfigurator->import(NetteSetList::NETTE_UTILS_CODE_QUALITY);

//	$parameters->set(Option::SKIP, [
//		// Chci používat != null namísto instance of
//		Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector::class,
//		// Netřeba takhle striktně držet DateTimeInstance
//		Rector\CodeQuality\Rector\ClassMethod\DateTimeToDateTimeInterfaceRector::class,
//		// Odstraňuje use Template
//		Rector\Renaming\Rector\Name\RenameClassRector::class,
//		// Dává (string) i tam kde neni potřeba
//		Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector::class,
//	]);

	$parameters->set(Option::PHP_VERSION_FEATURES, PhpVersion::PHP_80);

	$services = $containerConfigurator->services();
	$services->set(ReturnTypeDeclarationRector::class);
};
