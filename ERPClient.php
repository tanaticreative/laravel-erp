<?php


namespace Tan\ERP;

use Tan\ERP\Entities\BaseEntity;
use Tan\ERP\Entities\CompanyCustomer;
use Tan\ERP\Entities\Sales\Invoice;
use Tan\ERP\Exceptions\ApiNotFoundException;
use Tan\ERP\Exceptions\ApiOptimisticLockException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Log\LogLevel;

use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ERPClient
 * @package Tan\ERP
 *
 * [ base url: /webapp/api/v1 , api version: 1 ]
 *
 * OLD DOCS https://www.weclapp.com/api2/
 * FRESH DOCS https://etsolxaelpocgnk.weclapp.com/webapp/view/api/#/webhook
 *
 * TODO: add ?properties=
 */
final class ERPClient
{
    protected $baseUrl;
    protected $http;


    public function __construct()
    {
        $baseUrl = Config::get('erp.api.base_url');
        $baseUrl = Str::endsWith($baseUrl, '/') ? $baseUrl : $baseUrl . '/';
        $this->baseUrl = $baseUrl;

        // NOTE: this logger introduces bug https://github.com/8p/EightPointsGuzzleBundle/issues/48
        // WORKAROUND: __toString() on body content
        $stack = HandlerStack::create();
        $stack->push(static::log(Log::channel('erp'), new MessageFormatter("{request}\n\n{response}"), LogLevel::DEBUG));

        $this->http = new Client([
            'base_uri' => $this->baseUrl,
            'handler' => $stack,
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::DEBUG => false,
            RequestOptions::TIMEOUT => 0,
            RequestOptions::COOKIES => false,
            RequestOptions::HEADERS => [
                'AuthenticationToken' => Config::get('erp.api.authtoken'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }


    /**
     * Middleware that logs requests, responses, and errors using a message formatter.
     *
     * Ignores logging of files
     *
     * NOTE: Copy-paste of GuzzleHttp\Middleware
     * @see \GuzzleHttp\Middleware::log()
     *
     * @param LoggerInterface  $logger Logs messages.
     * @param MessageFormatter $formatter Formatter used to create message strings.
     * @param string           $logLevel Level at which to log requests.
     *
     * @return callable Returns a function that accepts the next handler.
     */
    public static function log(LoggerInterface $logger, MessageFormatter $formatter, $logLevel = LogLevel::INFO)
    {
        return function (callable $handler) use ($logger, $formatter, $logLevel) {
            return function ($request, array $options) use ($handler, $logger, $formatter, $logLevel) {
                return $handler($request, $options)->then(
                    function ($response) use ($logger, $request, $formatter, $logLevel) {
                        // workaround -->
                        /** @var ResponseInterface $response */
                        if (in_array('application/pdf', $response->getHeader('Content-Type'))) {
                            $message = $formatter->format($request, null);
                        } else {
                            $message = $formatter->format($request, $response);
                        }
                        // <---
                        $logger->log($logLevel, $message);
                        return $response;
                    },
                    function ($reason) use ($logger, $request, $formatter) {
                        $response = $reason instanceof RequestException
                            ? $reason->getResponse()
                            : null;
                        $message = $formatter->format($request, $response, $reason);
                        $logger->notice($message);
                        return \GuzzleHttp\Promise\rejection_for($reason);
                    }
                );
            };
        };
    }


    /**
     * @param string $entityClass
     * @param array $where
     * @param bool $withNulls
     * @return int
     */
    public function count($entityClass, array $where = [], $withNulls = false)
    {
        $params = [];
        if ($where) {
            $params = $this->buildWhere($where);
        }
        $query = http_build_query($params);
        if ($withNulls) {
            $query = implode('&', ['serializeNulls', $query]);
        }
        try {
            $response = $this->http->get($entityClass::ENTITY_NAME . '/count'. '?' . $query);
        } catch (ClientException $e) {
            Log::channel('erp')->error("Failed to count entity", ['entityClass' => $entityClass]);
            throw $e;
        }

        $data = json_decode((string)$response->getBody(), true);

        return $data['result'] ?? 0;
    }


    /**
     * @param $entityType
     * @param array $sorting
     * @param array $where
     * @param bool $withNulls
     * @param int $page
     * @param int $pageSize
     * @return array
     */
    public function query($entityType, array $sorting = [], array $where = [], $withNulls = false, $page = 1, $pageSize = 100)
    {
        $params = [];
        $params['page'] = $page;
        $params['pageSize'] = $pageSize;

        //
        $sortOptions = [];
        foreach ($sorting as $field => $dir) {
            $sortOptions[] = strtolower($dir) == 'asc' ? $field : "-$field";
        }
        if ($sortOptions) {
            $params['sort'] = implode(',', $sortOptions);
        }

        $params = array_merge($params, $this->buildWhere($where));
        $query = http_build_query($params);

        if ($withNulls) {
            $query = implode('&', ['serializeNulls', $query]);
        }

        $response = $this->http->get($entityType::ENTITY_NAME . '?' . $query);
        $data = json_decode((string)$response->getBody(), true);

        return $data['result'] ?? [];
    }


    protected function buildWhere(array $where)
    {
        $params = [];
        $whereOperators = [
            '=' => 'eq', '!=' => 'ne', '<' => 'lt', '>' => 'gt', '<=' => 'le',
            '>=' => 'ge', 'null' => 'null', 'notnull' => 'notnull', 'like' => 'like',
            'notlike' => 'notlike', 'ilike' => 'ilike',
            'in' => 'in' // MUST be JSON array
        ];
        foreach ($where as $whereItem) {
            if (empty($whereOperators[$whereItem['oper']])) {
                throw new \InvalidArgumentException("Operator '{$whereItem['oper']}' is not supported!");
            }
            $params["{$whereItem['col']}-{$whereOperators[$whereItem['oper']]}"] = is_array($whereItem['val']) ? json_encode($whereItem['val']) : $whereItem['val'];
        }

        return $params;
    }


    /**
     * Finds ERP entity by its type and ID
     *
     * @param string $entityClass
     * @param string $entityId
     * @return array|null
     */
    public function find($entityClass, $entityId)
    {
        if ($entityId === null) {
            return null;
        }

        try {
            $response = $this->http->get($entityClass::ENTITY_NAME . '/id/' . $entityId);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                return null;
            }
            throw $e;
        }

        return json_decode((string)$response->getBody(), true);
    }


    /**
     * Returns pdf file
     * @param Invoice $invoice
     * @return string
     */
    public function downloadLatestSalesInvoicePdf(Invoice $invoice)
    {
        $response = $this->http->get("salesInvoice/id/{$invoice->id}/downloadLatestSalesInvoicePdf");

        return $response->getBody()->getContents();
    }



    /**
     * Adds comment to particular entity
     *
     * TODO: make as entity when have some time
     *
     * @param BaseEntity $entity
     * @param string $comment
     * @return array
     */
    public function addComment(BaseEntity $entity, string $comment)
    {
        $data = [
            'entityName' => $entity::ENTITY_NAME,
            'entityId' => $entity->id,
            'comment' => $comment
        ];
        $response = $this->http->post('comment', ['body' => json_encode($data)]);

        return json_decode((string)$response->getBody(), true);
    }


    /**
     * @param BaseEntity $entity
     * @return array
     */
    public function create(BaseEntity $entity)
    {
        $response = $this->http->post($entity::ENTITY_NAME, ['body' => $entity]);

        return json_decode((string)$response->getBody(), true);
    }


    /**
     * @param BaseEntity $entity
     * @throws ApiNotFoundException
     * @throws ApiOptimisticLockException
     * @return array
     */
    public function update(BaseEntity $entity)
    {
        try {
            $response = $this->http->put($entity::ENTITY_NAME . '/id/' . $entity->id, ['body' => $entity]);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new ApiNotFoundException("Entity ({$entity->id}). " . (string)$e->getResponse()->getBody());
            }
            if ($e->getResponse()->getStatusCode() === 409) {
                throw new ApiOptimisticLockException("Entity ({$entity->id}). " . (string)$e->getResponse()->getBody());
            }
            throw $e;
        }

        return json_decode((string)$response->getBody(), true);
    }


    public function delete(BaseEntity $entity)
    {
        $entityName = $entity::ENTITY_NAME;

        try {
            $response = $this->http->delete($entityName . '/id/' . $entity->id);
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 204) {
                return;
            }
            if ($e->getResponse()->getStatusCode() === 404) {
                Log::channel('erp')->warning("Entity '{$entityName}' ({$entity->id}) was already deleted");
                return;
            }
            throw $e;
        }
    }


    /**
     * @param BaseEntity $entity
     * @throws ApiNotFoundException
     * @throws ApiOptimisticLockException
     * @return CompanyCustomer
     */
    public function convertLeadToCompanyCustomer(BaseEntity $entity)
    {
        try {
            $response = $this->http->get($entity::ENTITY_NAME . "/id/{$entity->id}/convertLeadToCustomer");
        } catch (ClientException $e) {
            if ($e->getResponse()->getStatusCode() === 404) {
                throw new ApiNotFoundException("Entity ({$entity->id}). " . (string)$e->getResponse()->getBody());
            }
            if ($e->getResponse()->getStatusCode() === 409) {
                throw new ApiOptimisticLockException("Entity ({$entity->id}). " . (string)$e->getResponse()->getBody());
            }
            throw $e;
        }

        $data = json_decode((string)$response->getBody(), true);

        return new CompanyCustomer($data['result']);
    }
}
