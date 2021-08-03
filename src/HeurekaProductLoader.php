<?php

declare(strict_types=1);

namespace Baraja\Shop\ProductLoader;


use Baraja\Doctrine\EntityManager;
use Baraja\DynamicConfiguration\Configuration;
use Baraja\Heureka\CategoryManager;
use Baraja\Heureka\Delivery;
use Baraja\Heureka\HeurekaProduct;
use Baraja\Heureka\ProductLoader;
use Baraja\Shop\Product\Entity\Product;
use Baraja\Shop\Product\Entity\ProductVariant;
use Baraja\Shop\ShopInfo;
use Baraja\Url\Url;
use Nette\Application\LinkGenerator;

final class HeurekaProductLoader implements ProductLoader
{
	public function __construct(
		private EntityManager $entityManager,
		private CategoryManager $categoryManager,
		private Configuration $configuration,
		private ShopInfo $shopInfo,
		private MessageManager $messageManager,
		private ?LinkGenerator $linkGenerator = null,
	) {
	}


	/**
	 * @return HeurekaProduct[]
	 */
	public function getProducts(): array
	{
		/** @var Product[] $products */
		$products = $this->entityManager->getRepository(Product::class)
			->createQueryBuilder('product')
			->select('product, mainCategory, mainImage, image, parameter, variant, smartDescription')
			->leftJoin('product.mainCategory', 'mainCategory')
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
				$this->messageManager->handle($product, $e->getMessage());
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
		$heurekaCategoryId = $mainCategory !== null
			? $mainCategory->getHeurekaCategoryId()
			: null;
		if ($heurekaCategoryId === null) {
			throw new \InvalidArgumentException('Heureka category does not exist.');
		}
		if ($mainCategory !== null) {
			$manufacturer = $product->getManufacturer();
			$item = new HeurekaProduct(
				itemId: (string) $product->getId(),
				product: (string) $product->getName(),
				productName: (string) $product->getName(),
				url: $this->getProductLink($product),
				priceVat: $product->getPrice(),
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
			$ean = $product->getEan();
			if ($ean !== null) {
				$item->setEan($ean);
			}
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
		$return->setPriceVat($variant->getPrice());
		$return->setEan((string) $variant->getEan());
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
			$return .= ($return ? "\n\n<hr>\n\n" : '') . strip_tags((string) $smartDescription->getDescription());
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
		// TODO: Load available deliveries automatically
		return [
			new Delivery(Delivery::ZASILKOVNA, 82, 97),
			new Delivery(Delivery::DPD, 145, 160),
			new Delivery(Delivery::GLS, 120, 145),
		];
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
			return Url::get()->getBaseUrl()
				. '/' . $product->getSlug()
				. ($variant !== null ? '&variant=' . $variant->getId() : '');
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
}
