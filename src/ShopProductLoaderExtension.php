<?php

declare(strict_types=1);

namespace Baraja\Shop\ProductLoader;


use Baraja\Doctrine\ORM\DI\OrmAnnotationsExtension;
use Nette\DI\CompilerExtension;

final class ShopProductLoaderExtension extends CompilerExtension
{
	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();
		OrmAnnotationsExtension::addAnnotationPathToManager(
			$builder, 'Baraja\Shop\ProductLoader\Entity', __DIR__ . '/Entity'
		);

		$builder->addDefinition($this->prefix('heurekaProductLoader'))
			->setFactory(HeurekaProductLoader::class);

		$builder->addDefinition($this->prefix('messageManager'))
			->setFactory(MessageManager::class);
	}
}
