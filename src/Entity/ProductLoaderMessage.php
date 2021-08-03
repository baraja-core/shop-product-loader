<?php

declare(strict_types=1);

namespace Baraja\Shop\ProductLoader\Entity;


use Baraja\Doctrine\Identifier\IdentifierUnsigned;
use Baraja\Shop\Product\Entity\Product;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\Entity;
use Doctrine\ORM\Mapping\ManyToOne;
use Doctrine\ORM\Mapping\Table;

#[Entity]
#[Table(name: 'shop__product_loader_message')]
class ProductLoaderMessage
{
	use IdentifierUnsigned;

	public const
		LEVEL_INFO = 'info',
		LEVEL_MESSAGE = 'message',
		LEVEL_ERROR = 'error',
		LEVEL_CRITICAL = 'critical';

	#[ManyToOne(targetEntity: Product::class)]
	private Product $product;

	#[Column(type: 'string', length: 32, unique: true)]
	private string $hash;

	#[Column(type: 'text')]
	private string $message;

	#[Column(type: 'string', length: 16)]
	private string $level;

	#[Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;

	#[Column(type: 'datetime')]
	private \DateTimeInterface $updatedDate;


	public function __construct(Product $product, string $message, string $level = self::LEVEL_INFO)
	{
		$this->product = $product;
		$this->message = $message;
		$this->level = $level;
		$this->hash = md5($message);
		$this->insertedDate = new \DateTimeImmutable;
		$this->updateNow();
	}


	public function getProduct(): Product
	{
		return $this->product;
	}


	public function getHash(): string
	{
		return $this->hash;
	}


	public function getMessage(): string
	{
		return $this->message;
	}


	public function getLevel(): string
	{
		return $this->level;
	}


	public function updateNow(): bool
	{
		$this->updatedDate = new \DateTimeImmutable;

		return false;
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}


	public function getUpdatedDate(): \DateTimeInterface
	{
		return $this->updatedDate;
	}
}
