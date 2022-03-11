<?php

declare(strict_types=1);

namespace Baraja\Shop\ProductLoader;


use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\ProductLoader\Entity\ProductLoaderMessage;
use Baraja\Shop\ProductLoader\Repository\ProductLoaderMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class MessageManager
{
	private ProductLoaderMessageRepository $productLoaderMessageRepository;

	/** @var array<string, ProductLoaderMessage> */
	private array $usedHashes = [];

	private bool $needFlush = false;


	public function __construct(
		private EntityManagerInterface $entityManager,
	) {
		$productLoaderMessageRepository = $entityManager->getRepository(ProductLoaderMessage::class);
		assert($productLoaderMessageRepository instanceof ProductLoaderMessageRepository);
		$this->productLoaderMessageRepository = $productLoaderMessageRepository;
	}


	public function log(
		Product $product,
		string $message,
		string $level = ProductLoaderMessage::LEVEL_INFO,
	): ProductLoaderMessage {
		$log = new ProductLoaderMessage($product, $message, $level);
		$hash = $log->getHash();
		if (isset($this->usedHashes[$hash])) {
			return $this->usedHashes[$hash];
		}

		try {
			$log = $this->productLoaderMessageRepository->getByHash($hash);
			if ($log->updateNow()) { // has been changed?
				$this->needFlush = true;
			}
		} catch (NoResultException|NonUniqueResultException) {
			$this->entityManager->persist($log);
			$this->needFlush = true;
		}
		$this->usedHashes[$hash] = $log;

		return $log;
	}


	public function flush(): void
	{
		if ($this->needFlush === true) {
			$this->entityManager->flush();
			$this->needFlush = false;
		}
	}
}
