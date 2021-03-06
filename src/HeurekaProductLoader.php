<?php

declare(strict_types=1);

namespace Baraja\Shop\ProductLoader;


use Baraja\Heureka\CategoryManager;
use Baraja\Heureka\Delivery;
use Baraja\Heureka\HeurekaProduct;
use Baraja\Heureka\ProductLoader;
use Baraja\Shop\Delivery\Entity\Delivery as DeliveryEntity;
use Baraja\Shop\Delivery\Repository\DeliveryRepository;
use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\Product\Entity\ProductVariant;
use Baraja\Shop\Product\Repository\ProductRepository;
use Baraja\Shop\ShopInfo;
use Baraja\Url\Url;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Application\LinkGenerator;

final class HeurekaProductLoader implements ProductLoader
{
	private DeliveryRepository $deliveryRepository;

	private ProductRepository $productRepository;

	/** @var array<int, DeliveryEntity>|null */
	private ?array $deliveryList = null;


	public function __construct(
		EntityManagerInterface $entityManager,
		private CategoryManager $categoryManager,
		private ShopInfo $shopInfo,
		private MessageManager $messageManager,
		private ?LinkGenerator $linkGenerator = null,
	) {
		$deliveryRepository = $entityManager->getRepository(DeliveryEntity::class);
		assert($deliveryRepository instanceof DeliveryRepository);
		$this->deliveryRepository = $deliveryRepository;
		$productRepository = $entityManager->getRepository(Product::class);
		assert($productRepository instanceof ProductRepository);
		$this->productRepository = $productRepository;
	}


	/**
	 * @return HeurekaProduct[]
	 */
	public function getProducts(): array
	{
		/** @var array<int, Product> $products */
		$products = $this->productRepository
			->createQueryBuilder('product')
			->select('product, mainCategory, mainImage, image, parameter, variant, smartDescription')
			->join('product.mainCategory', 'mainCategory')
			->leftJoin('product.mainImage', 'mainImage')
			->leftJoin('product.images', 'image')
			->leftJoin('product.parameters', 'parameter')
			->leftJoin('product.variants', 'variant')
			->leftJoin('product.smartDescriptions', 'smartDescription')
			->where('mainImage.id IS NOT NULL')
			->andWhere('product.active = TRUE')
			->andWhere('product.soldOut = FALSE')
			->andWhere('product.showInFeed = TRUE')
			->getQuery()
			->getResult();

		$return = [];
		foreach ($products as $product) {
			try {
				$return[] = $this->mapProduct($product);
			} catch (\InvalidArgumentException $e) {
				$this->messageManager->log($product, $e->getMessage());
			}
		}
		$this->messageManager->flush();

		return array_merge([], ...$return);
	}


	/**
	 * @return HeurekaProduct[]
	 */
	private function mapProduct(Product $product): array
	{
		$return = [];
		$mainCategory = $product->getMainCategory();
		$heurekaCategoryId = $mainCategory?->getHeurekaCategoryId();
		if ($heurekaCategoryId === null) {
			throw new \InvalidArgumentException('Heureka category does not exist.');
		}
		if ($mainCategory !== null) {
			$manufacturer = $product->getManufacturer();
			$item = new HeurekaProduct(
				itemId: (string) $product->getId(),
				product: $product->getLabel(),
				productName: $product->getLabel(),
				url: $this->getProductLink($product),
				priceVat: (float) $product->getPrice(),
				category: $this->categoryManager->getCategory($heurekaCategoryId),
				manufacturer: $manufacturer !== null
					? $manufacturer->getName()
					: $this->getDefaultManufacturer(),
			);
			$item->setDescription($this->processDescription($product));
			if ($product->isSale()) {
				$item->addCustomTag('SALE_PRICE', $product->getSalePrice());
			}
			$mainImage = $product->getMainImage();
			if ($mainImage !== null) {
				$item->setImgUrl($mainImage->getUrl());
			}
			foreach ($product->getImages() as $image) {
				$item->addImgUrlAlternative($image->getUrl());
			}
			$item->setDeliveryDate(1);
			$item->setEan($product->getEan());
			$params = [];
			foreach ($product->getParameters() as $parameter) {
				if ($parameter->isVariant() === false) {
					$params[$parameter->getName()] = implode(', ', $parameter->getValues());
				}
			}
			$item->setParams($params);
			$item->setHeurekaCpc(5);
			$item->setDeliveries($this->getDelivery($product));
			$item->addCustomTag('ITEMGROUP_ID', (string) $product->getId());

			$return[] = $item;
			$variants = $product->getVariants();
			if (count($variants) > 1) {
				foreach ($variants as $variant) {
					$return[] = $this->mapVariant($item, $variant);
				}
			}
		}

		return $return;
	}


	private function mapVariant(HeurekaProduct $item, ProductVariant $variant): HeurekaProduct
	{
		$product = $variant->getProduct();
		$return = clone $item;
		$return->setItemId($product->getId() . '-' . $variant->getId());
		$return->addCustomTag('ITEMGROUP_ID', (string) $product->getId());
		$return->setProductName($variant->getLabel());
		$return->setPriceVat((float) $variant->getPrice());
		$return->setEan($variant->getEan());
		$return->setParams(ProductVariant::unserializeParameters($variant->getRelationHash()));
		$return->setUrl($this->getProductLink($product, $variant));

		return $return;
	}


	private function processDescription(Product $product): string
	{
		static $defaultDescription;
		if ($defaultDescription === null) {
			$defaultDescription = (string) $this->shopInfo->getShopDescription();
		}

		$return = strip_tags((string) $product->getShortDescription());
		foreach ($product->getSmartDescriptions() as $smartDescription) {
			$return .= ($return !== '' ? "\n\n<hr>\n\n" : '');
			$return .= strip_tags((string) $smartDescription->getDescription());
		}
		if ($return === '') {
			$return = $defaultDescription;
		}

		return $return;
	}


	/**
	 * @return Delivery[]
	 */
	private function getDelivery(Product $product): array
	{
		$return = [];
		foreach ($this->getAllDeliveries() as $delivery) {
			$priceCod = $delivery->getPriceCod();
			try {
				$return[] = new Delivery(
					id: strtoupper($delivery->getCode()),
					price: (float) $delivery->getPrice(),
					priceCod: $priceCod !== null ? (float) $priceCod : null,
				);
			} catch (\InvalidArgumentException) {
			}
		}

		return $return;
	}


	private function getProductLink(Product $product, ?ProductVariant $variant = null): string
	{
		$params = [
			'slug' => $product->getSlug(),
		];
		if ($variant !== null) {
			$params['variant'] = $variant->getId();
		}
		if ($this->linkGenerator === null) {
			return sprintf(
				'%s/%s%s',
				Url::get()->getBaseUrl(),
				$product->getSlug(),
				$variant !== null ? '&variant=' . $variant->getId() : '',
			);
		}

		return $this->linkGenerator->link('Front:Product:detail', $params);
	}


	private function getDefaultManufacturer(): string
	{
		static $cache;
		if ($cache === null) {
			$cache = $this->shopInfo->getDefaultManufacturer();
		}

		return $cache;
	}


	/**
	 * @return array<int, DeliveryEntity>
	 */
	private function getAllDeliveries(): array
	{
		if ($this->deliveryList === null) {
			$this->deliveryList = $this->deliveryRepository->findAll();
		}

		return $this->deliveryList;
	}
}
