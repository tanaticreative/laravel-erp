<?php


namespace Tan\ERP\Entities;

use Tan\ERP\Contracts\EntityEvent;
use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Entities\Sales\Invoice;
use Tan\ERP\Exceptions\NotSupportedByAGRException;
use Tan\ERP\Support\Facade;
use App\Components\ERP\Entities\Sales\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;

/**
 * Webhook ERP Entity
 *
 * {@inheritdoc}
 *
 * @property string $entityName
 * @property bool $atCreate
 * @property bool $atDelete
 * @property bool $atUpdate
 * @property Carbon|null $deactivatedDate
 * @property string|null $errorMessage
 * @property string $requestMethod POST|GET|DELETE
 * @property string $url full url for webhook
 */
class Webhook extends BaseEntity
{
    const ENTITY_NAME = 'webhook';

    protected $casts = [
        'atCreate' => 'boolean',
        'atDelete' => 'boolean',
        'atUpdate' => 'boolean',
        'lastModifiedDate' => 'datetime:Uv',
        'createdDate' => 'datetime:Uv',
        'deactivatedDate' => 'datetime:Uv',
    ];

    /**
     * {@inheritdoc}
     */
    public function save(array $options = [])
    {
        $erpEntity = parent::find($this->id);
        if ($erpEntity) {
            // ERP wants all data for entity so we give it
            $this->fill(array_merge($erpEntity->toArray(), $this->toArray()));
            $data = Facade::getClient()->update($this);
            $this->fill($data);
            $event = 'updated';
        } else {
            $data = Facade::getClient()->create($this);
            $this->fill($data);
            $event = 'created';
        }

        $this->syncOriginal();

        Event::dispatch(EntityEvent::class, [$this, $event]);
    }


    /**
     * All required webhooks by our ERP integrator
     *
     * @return static[]
     */
    protected static function requiredWebhooks()
    {
        $actionsMap = ['atCreate' => 'created', 'atDelete' => 'deleted', 'atUpdate' => 'updated'];
        $entities = [
            // name => supported actions by AGR fro mERP
            Contact::ENTITY_NAME => ['atUpdate', 'atDelete'],
            CompanyCustomer::ENTITY_NAME => ['atUpdate'],
            CompanyLead::ENTITY_NAME => ['atUpdate'],
            Article::ENTITY_NAME => ['atUpdate'],
            Invoice::ENTITY_NAME => ['atCreate', 'atUpdate'],
         //   Order::ENTITY_NAME => ['atUpdate']
        ];

        $webhooks = [];
        foreach ($entities as $entityName => $actions) {
            foreach ($actions as $action) {
                $webhook = new static();
                $webhook->entityName = $entityName;
                $webhook->requestMethod = 'POST';
                $webhook->atCreate = false;
                $webhook->atDelete = false;
                $webhook->atUpdate = false;
                $webhook->{$action} = true;
                $params = [
                    'a' => $actionsMap[$action],
                    'c' => Config::get('erp.webhook.client'),
                    's' => Config::get('erp.webhook.secret'),
                ];
                $webhook->url = Config::get('app.url') .'/erp/webhook?' . http_build_query($params);
                if (app()->environment('local')) { //TODO: fix hardcode
                    $webhook->url = 'https://staging.Tan.com'.'/erp/webhook?' . http_build_query($params);
                }
                $webhooks[] = $webhook;
            }
        }

        return $webhooks;
    }


    /**
     * Init cached data
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function init()
    {
        Cache::delete(get_called_class() . ':all');
        Cache::rememberForever(get_called_class() . ':all', function () {
            $items = static::query()->get();
            $collection = Collection::make($items)->keyBy(function ($item, $key) {
                return $item->id;
            });

            foreach (static::requiredWebhooks() as $requiredWebhook) {
                $webhook = $collection->first(function ($item) use ($requiredWebhook) {
                    return $item->entityName === $requiredWebhook->entityName &&
                        $item->requestMethod === $requiredWebhook->requestMethod &&
                        $item->atCreate === $requiredWebhook->atCreate &&
                        $item->atDelete === $requiredWebhook->atDelete &&
                        $item->atUpdate === $requiredWebhook->atUpdate &&
                        $item->url === $requiredWebhook->url;
                });
                if (!$webhook) {
                    $requiredWebhook->save();
                    $collection->put($requiredWebhook->id, $requiredWebhook);
                }
            }

            return $collection;
        });
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
    public function syncModel()
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
    public function fillFromModel(EntitySyncState $model)
    {
        throw new NotSupportedByAGRException();
    }
}
