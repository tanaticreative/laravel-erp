<?php


namespace Tan\ERP\Entities\FakeArticle;

use Tan\ERP\Entities\Article;
use Tan\ERP\Entities\ArticleCategory;
use Tan\ERP\Entities\Sales\Channel;
use Tan\ERP\Exceptions\ApiNotFoundException;
use Tan\ERP\Exceptions\NotSupportedByAGRException;
use Tan\ERP\Support\Facade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use App\Models\Category as MCategory;
use Illuminate\Support\Facades\Log;

/**
 * {@inheritdoc}
 */
class GoodsCategory extends Article
{
    // 0.012 * 100 = 1.2%
    const FEE_PERCENT = 0.012;

    // Fee
    const CATEGORY_GOODS_SEEDS = 10000;
    const CATEGORY_GOODS_FERTILIZER = 10010;
    const CATEGORY_GOODS_PLANT_PROTECTION = 10020;
    const CATEGORY_GOODS_GOODS_GRAIN = 10030;
    const CATEGORY_GOODS_OILSEEDS = 10040;
    const FLASH_OFFER_CATEGORY_GOODS_SEEDS = 11000;
    const FLASH_CATEGORY_GOODS_FERTILIZER = 11010;
    const FLASH_CATEGORY_GOODS_PLANT_PROTECTION = 11020;
    const FLASH_CATEGORY_GOODS_GOODS_GRAIN = 11030;
    const FLASH_CATEGORY_GOODS_OILSEEDS = 11040;


    public static $mappedCategories = [
        // fake goods category article number => internal goods category ID
        self::CATEGORY_GOODS_SEEDS => MCategory::GOODS_SEEDS,
        self::CATEGORY_GOODS_FERTILIZER => MCategory::GOODS_FERTILIZER,
        self::CATEGORY_GOODS_PLANT_PROTECTION => MCategory::GOODS_PLANT_PROTECTION,
        self::CATEGORY_GOODS_GOODS_GRAIN => MCategory::GOODS_GRAIN,
        self::CATEGORY_GOODS_OILSEEDS => MCategory::GOODS_OILSEEDS,
        self::FLASH_OFFER_CATEGORY_GOODS_SEEDS => MCategory::GOODS_SEEDS,
        self::FLASH_CATEGORY_GOODS_FERTILIZER => MCategory::GOODS_FERTILIZER,
        self::FLASH_CATEGORY_GOODS_PLANT_PROTECTION => MCategory::GOODS_PLANT_PROTECTION,
        self::FLASH_CATEGORY_GOODS_GOODS_GRAIN => MCategory::GOODS_GRAIN,
        self::FLASH_CATEGORY_GOODS_OILSEEDS => MCategory::GOODS_OILSEEDS,
    ];


    /**
     * @param MCategory $category
     * @return static
     * @throws ApiNotFoundException
     */
    public static function findByModel(MCategory $category, $isFixed)
    {
        $articleNumber = self::getCategoryProduct($category, $isFixed);

        $fakeCategory = static::all()->first(function ($item) use ($articleNumber) {
            return $item->articleNumber == $articleNumber;
        });

        if (!$fakeCategory) {
            throw new ApiNotFoundException("Fake goods category by articleNumber '$articleNumber' was not found for category '$category->id'. Please add it manually at ERP! or run '" . static::class . "::init()'");
        }

        return $fakeCategory;
    }

    public static function getCategoryProduct(MCategory $category, $isFixed)
    {
        $const = new \ReflectionClass(__CLASS__);

        $flashProducts = array_filter($const->getConstants(), function ($k) {
            return strpos($k, 'FLASH') === 0;
        }, ARRAY_FILTER_USE_KEY);

        //  $articleNumber = array_flip(static::$mappedCategories)[$category->id];
        $articleNumber = array_filter(static::$mappedCategories, function ($v, $k) use ($category, $isFixed, $flashProducts) {
            return ($v == $category->id && (($isFixed && in_array($k, $flashProducts)) || (!$isFixed && !in_array($k, $flashProducts))));
        }, ARRAY_FILTER_USE_BOTH);

        return array_key_first($articleNumber);
    }


    public static function init()
    {
        Cache::delete(get_called_class() . ':all');
        Cache::rememberForever(get_called_class() . ':all', function () {
            $fakeCategories = [];
            foreach (MCategory::getGoods() as $goodsCategory) {
                foreach ([0, 1] as $isFixed) {
                    $fakeCategories[] = self::addCategory($goodsCategory, $isFixed);
                }
            }
            return Collection::make($fakeCategories)->keyBy(function ($item, $key) {
                return $item->id;
            });
        });
    }


    /**
     * @param MCategory $category
     * @return static
     */
    protected static function addCategory(MCategory $category, $isFixed)
    {
        $goodsCategoryArticle = ArticleCategory::findByName(ArticleCategory::CATEGORY_FEE_NAME);
        $articleNumber = self::getCategoryProduct($category, $isFixed);
        $fakeGoodsCategory = static::query()->where('articleNumber', '=', $articleNumber)->get()->first();

        if ($fakeGoodsCategory) {
            Log::channel('erp')->warning('Fake Goods Category was already added. Skipping', ['category' => $category, 'articleId' > $fakeGoodsCategory->id]);
            return $fakeGoodsCategory;
        }
        $unitEUR = "EUR";
        $article = new static([
            "active" => true,
            "applyCashDiscount" => true,
            "articleCategoryId" => $goodsCategoryArticle->id,
            "articleImages" => [],
            "articleNumber" => $articleNumber,
            "articlePrices" => [
                [
                    "currencyName" => $unitEUR,
                    "positionNumber" => 1,
                    "price" => self::FEE_PERCENT,
                    "priceScaleType" => "SCALE_FROM",
                    "priceScaleValue" => "0",
                    "reductionAdditions" => [],
                    "salesChannel" => Channel::CHANNEL_KEY_FEE
                ]
            ],
            "articleType" => Article::ARTICLE_TYPE_BASIC,
            "availableForSalesChannels" => [],
            "availableInSale" => true,
            "batchNumberRequired" => false,
            "billOfMaterialPartDeliveryPossible" => false,
            "customAttributes" => [],
            "defaultWarehouseLevels" => [],
            "differentMinimumStockQuantities" => [],
            "marginCalculationPriceType" => "PURCHASE_PRICE_PRODUCTION_COST",
            "name" => ($isFixed? 'Blitzangebot ' : 'Ausschreibungen ') . $category->name,
            "productionArticle" => false,
            "serialNumberRequired" => false,
            "supplySources" => [],
            "tags" => [],
            "taxRateType" => "STANDARD",
            "unitName" => $unitEUR,
            "useAvailableForSalesChannels" => false,
            "useSalesBillOfMaterialItemPrices" => false,
            "useSalesBillOfMaterialItemPricesForPurchase" => false
        ]);

        return new static(Facade::getClient()->create($article));
    }


    /**
     * {@inheritdoc}
     */
    public static function all()
    {
        if (!Cache::has(get_called_class() . ':all')) {
            static::init();
        }

        return Cache::get(get_called_class() . ':all');
    }


    /**
     * {@inheritdoc}
     */
    public static function find($id)
    {
        return static::all()->get($id)->first();
    }


    /**
     * {@inheritdoc}
     */
    public function save(array $options = [])
    {
        throw new NotSupportedByAGRException();
    }


    /**
     * {@inheritdoc}
     */
    public function createModel()
    {
        throw new NotSupportedByAGRException();
    }


    /**
     * {@inheritdoc}
     */
    public function syncModel()
    {
        throw new NotSupportedByAGRException();
    }
}
