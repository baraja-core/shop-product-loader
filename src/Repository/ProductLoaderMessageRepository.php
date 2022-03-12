<?php

declare(strict_types=1);

namespace Baraja\Shop\ProductLoader\Repository;


use Baraja\Shop\ProductLoader\Entity\ProductLoaderMessage;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;

final class ProductLoaderMessageRepository extends EntityRepository
{
	/**
	 * @throws NoResultException|NonUniqueResultException
	 */
	public function getByHash(string $hash): ProductLoaderMessage
	{
		$return = $this->createQueryBuilder('m')
			->where('m.hash = :hash')
			->setParameter('hash', $hash)
			->setMaxResults(1)
			->getQuery()
			->getSingleResult();
		assert($return instanceof ProductLoaderMessage);

		return $return;
	}
}
