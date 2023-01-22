<?php


namespace Tan\ERP\Entities;

use Tan\ERP\Contracts\EntitySyncState;
use Tan\ERP\Exceptions\NotSupportedByERPException;
use App\Models\GeoIp\Location;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Address ERP Entity
 *
 * @property string $company @deprecated
 * @property string $countryCode
 * @property string $city
 * @property string $state
 * @property string $street1
 * @property string $street2
 * @property string $zipcode
 * @property bool $deliveryAddress
 * @property bool $invoiceAddress
 * @property bool $primeAddress
 *
 * @property \App\Models\Company\Address $model
 */
class Address extends BaseEntity
{
    protected $attributes = [
        'deliveryAddress' => true,
        'invoiceAddress' => true,
        'primeAddress' => true,
    ];
    protected $casts = [
        'invoiceAddress' => 'boolean',
        'deliveryAddress' => 'boolean',
        'primeAddress' => 'boolean',
        'lastModifiedDate' => 'datetime:Uv',
        'createdDate' => 'datetime:Uv',
    ];


    /**
     * {@inheritdoc}
     */
    public function save(array $options = [])
    {
        throw new NotSupportedByERPException();
    }


    /**
     * {@inheritdoc}
     */
    public function createModel()
    {
        throw new NotSupportedByERPException();
    }


    /**
     * {@inheritdoc}
     */
    public function syncModel()
    {
        $model = $this->model;
        if (!$model) {
            Log::channel('erp')->warning("Cannot sync model without model!", [ 'entity' => $this]);
            return;
        }

        $model->street = $this->street1;
        // TODO: remove hardcode
        if (mb_strlen($this->street2) > 5) {
            Log::channel('erp')->warning("street2 '$this->street2' is too long for AGR. Truncating..");
        }
      //  $model->house_number = Str::substr($this->street2, 0, 5);
        $model->plz = $this->zipcode;
        if ($model->is_delivery_address != $this->deliveryAddress) {
            Log::channel('erp')->warning("Cannot change 'is_delivery_address'");
            //$model->is_delivery_address = $this->deliveryAddress;
        }
        if ($model->is_billing_address != $this->invoiceAddress) {
            Log::channel('erp')->warning("Cannot change 'is_billing_address'");
            //$model->is_billing_address = $this->invoiceAddress;
        }
        if ($model->isLegalAddress() && $model->isLegalAddress() != $this->primeAddress) {
            Log::channel('erp')->warning("Cannot change primary address to be secondary on AGR");
        }

        if ($city = $this->changeCity()) {
            if ($this->model->city_id !== $city->id) {
                Log::channel('erp')->info("City was changed");
            }
            $this->model->city_id = $city->id;
        }

        $model->save();
    }


    /**
     * Returns city if it can be changed successfully
     *
     * @return Location|null
     */
    protected function changeCity()
    {
        $city = Location::city()->whereName($this->city, '=', Config::get('erp.default_locale'))->first();
        if ($city) {
            if ($city->country_iso_code != $this->countryCode) {
                Log::channel('erp')->warning("ISO code does not match");
            }
            if ($city->name != $this->city) {
                Log::channel('erp')->warning("City name does not match '{$city->name}' != '{$this->city}'");
            }
            if ($city->subdivision_1_name != $this->state) {
                Log::channel('erp')->warning("City subdivision1/state does not match '{$city->subdivision_1_name}' != '{$this->state}'");
            }

            if ($city->name == $this->city && $city->subdivision_1_name == $this->state) {
                return $city;
            }
        }
        return null;
    }


    /**
     * {@inheritdoc}
     */
    public function fillFromModel(EntitySyncState $model)
    {
        if (!($model instanceof \App\Models\Company\Address)) {
            throw new \InvalidArgumentException("Only instance of " . \App\Models\Company\Address::class . ' is supported');
        }

        parent::fillFromModel($model);

        /** @var Location $cityTrans */
        $locale = Config::get('erp.default_locale');
        $cityTrans = $model->city->translateOrDefault($locale);
        $this->countryCode = $model->city->country_iso_code;
        $this->city = $cityTrans->name;
        $this->state = $cityTrans->subdivision_1_name;
        $this->street1 = $model->street .' '. $model->house_number;
      //  $this->street2 = $model->house_number;
        $this->zipcode = $model->plz;
        $this->deliveryAddress = $model->is_delivery_address;
        $this->invoiceAddress = $model->is_billing_address;
        $this->primeAddress = $model->isLegalAddress();
    }
}
