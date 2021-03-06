<?php

declare(strict_types=1);

namespace Baraja\Shop\ProductLoader\Entity;


use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\ProductLoader\Repository\ProductLoaderMessageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductLoaderMessageRepository::class)]
#[ORM\Table(name: 'shop__product_loader_message')]
class ProductLoaderMessage
{
	public const
		LevelInfo = 'info',
		LevelMessage = 'message',
		LevelError = 'error',
		LevelCritical = 'critical';

	#[ORM\Id]
	#[ORM\Column(type: 'integer', unique: true, options: ['unsigned' => true])]
	#[ORM\GeneratedValue]
	protected int $id;

	#[ORM\ManyToOne(targetEntity: Product::class)]
	private Product $product;

	#[ORM\Column(type: 'string', length: 32, unique: true)]
	private string $hash;

	#[ORM\Column(type: 'text')]
	private string $message;

	#[ORM\Column(type: 'string', length: 16)]
	private string $level;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $insertedDate;

	#[ORM\Column(type: 'datetime')]
	private \DateTimeInterface $updatedDate;


	public function __construct(Product $product, string $message, string $level = self::LevelInfo)
	{
		$this->product = $product;
		$this->message = $message;
		$this->level = $level;
		$this->hash = $this->createHash($product, $message);
		$this->insertedDate = new \DateTimeImmutable;
		$this->updateNow();
	}


	public function getId(): int
	{
		return $this->id;
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


	public function updateNow(): void
	{
		$this->updatedDate = new \DateTimeImmutable;
	}


	public function getInsertedDate(): \DateTimeInterface
	{
		return $this->insertedDate;
	}


	public function getUpdatedDate(): \DateTimeInterface
	{
		return $this->updatedDate;
	}


	private function createHash(Product $product, string $message): string
	{
		return sprintf(
			'%d_%s',
			$product->getId(),
			substr(md5($message), 0, strlen((string) $product->getId()) - 1),
		);
	}
}
