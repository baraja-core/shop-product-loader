<?php

declare(strict_types=1);

namespace Baraja\Shop\ProductLoader;


use Baraja\Doctrine\EntityManager;
use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\ProductLoader\Entity\ProductLoaderMessage;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class MessageManager
{
	/** @var array<string, ProductLoaderMessage> */
	private array $usedHashes = [];

	private bool $needFlush = false;


	public function __construct(
		private EntityManager $entityManager,
	) {
	}


	public function handle(
		Product $product,
		string $message,
		string $level = ProductLoaderMessage::LEVEL_INFO,
	): ProductLoaderMessage {
		$return = new ProductLoaderMessage($product, $message, $level);
		$hash = $return->getHash();
		if (isset($this->usedHashes[$hash])) {
			return $this->usedHashes[$hash];
		}

		try {
			/** @var ProductLoaderMessage $return */
			$return = $this->entityManager->getRepository(ProductLoaderMessage::class)
				->createQueryBuilder('m')
				->where('m.hash = :hash')
				->setParameter('hash', $hash)
				->setMaxResults(1)
				->getQuery()
				->getSingleResult();

			if ($return->updateNow()) { // has been changed?
				$this->needFlush = true;
			}
		} catch (NoResultException | NonUniqueResultException) {
			$this->entityManager->persist($return);
			$this->needFlush = true;
		}
		$this->usedHashes[$hash] = $return;

		return $return;
	}


	public function flush(): void
	{
		if ($this->needFlush === false) {
			return;
		}
		$this->entityManager->flush();
		$this->needFlush = false;
	}
}
