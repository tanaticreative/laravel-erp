<?php


namespace Tan\ERP\Entities\FakeArticle;

use Tan\ERP\Entities\Article;
use Tan\ERP\Entities\ArticleCategory;
use Tan\ERP\Entities\Sales\Channel;
use Tan\ERP\Exceptions\ApiNotFoundException;
use Tan\ERP\Exceptions\NotSupportedByAGRException;
use Tan\ERP\Support\Facade;
use \App\Models\Service as MService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Internal service representation on ERP as fake predefined article
 *
 * {@inheritdoc}
 */
class Service extends Article
{
    // TODO: document hardcode!
    const SERVICE_PROFESSIONAL_ANALYSIS = 12000;
    const SERVICE_CLEAR_NAME_PROPOSALS = 12010;
    const SERVICE_PROFESSIONAL_TRUST = 12020;

    public static $mappedServices = [
        // fake service article number => internal service ID
        self::SERVICE_PROFESSIONAL_ANALYSIS => MService::SERVICE_PROFESSIONAL_ANALYSIS,
        self::SERVICE_CLEAR_NAME_PROPOSALS => MService::SERVICE_CLEAR_NAME_PROPOSALS,
        self::SERVICE_PROFESSIONAL_TRUST => MService::SERVICE_TRUST
    ];


    /**
     * @param MService $service
     * @throws ApiNotFoundException
     * @return static|null
     */
    public static function findByModel(MService $service)
    {
        $articleNumber = array_flip(static::$mappedServices)[$service->id];
        $fakeService = static::all()->first(function ($item) use ($articleNumber) {
            return $item->articleNumber == $articleNumber;
        });

        if (!$fakeService) {
            throw new ApiNotFoundException("Fake service by articleNumber '$articleNumber' was not found for service '$service->id'. Please add it manually at ERP! or run '" . static::class . "::init()'");
        }

        return $fakeService;
    }


    public static function init()
    {
        Cache::delete(get_called_class() . ':all');
        Cache::rememberForever(get_called_class() . ':all', function () {
            $fakeServices = [];
            foreach (MService::all() as $service) {
                $fakeServices[] = self::addService($service);
            }

            return Collection::make($fakeServices)->keyBy(function ($item, $key) {
                return $item->id;
            });
        });
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
     * DUMMY INIT FOR TESTING!!! NOT READY FOR PRODUCTION USAGE!
     *
     * @param MService $service
     * @return static
     */
    protected static function addService(MService $service)
    {
        $serviceArticleCategory = ArticleCategory::findByName(ArticleCategory::CATEGORY_SERVICE_NAME);
        $articleNumber = array_flip(static::$mappedServices)[$service->id];
        $fakeService = static::query()->where('articleNumber', '=', $articleNumber)->get()->first();

        if ($fakeService) {
            Log::channel('erp')->warning('Fake service was already added. Skipping', ['service' => $service, 'articleId' > $fakeService->id]);
            return $fakeService;
        }

        $unitEUR = "EUR";

        $article = new static([
            "active" => true,
            "applyCashDiscount" => true,
            "articleCategoryId" => $serviceArticleCategory->id,
            "articleImages" => [],
            "articleNumber" => $articleNumber,
            "articlePrices" => [
                [
                    "currencyName" => $unitEUR,
                    "description" => "Gebühr die Sonderservices von Tan",
                    "positionNumber" => 1,
                    "price" => $service->price,
                    "priceScaleType" => "SCALE_FROM",
                    "priceScaleValue" => "0",
                    "reductionAdditions" => [],
                    "salesChannel" => Channel::CHANNEL_KEY_FEE,
                ]
            ],
            "articleType" => self::ARTICLE_TYPE_SERVICE,
            "availableForSalesChannels" => [],
            "availableInSale" => true,
            "batchNumberRequired" => false,
            "billOfMaterialPartDeliveryPossible" => false,
            "customAttributes" => [],
            "defaultWarehouseLevels" => [],
            "differentMinimumStockQuantities" => [],
            "marginCalculationPriceType" => "PURCHASE_PRICE_PRODUCTION_COST",
            "name" => $service->name,
            "productionArticle" => false,
            "productionBillOfMaterialItems" => [],
            "salesBillOfMaterialItems" => [],
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
