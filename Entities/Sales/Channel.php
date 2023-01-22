<?php


namespace Tan\ERP\Entities\Sales;

use Illuminate\Support\Collection;

/**
 * @property string $key
 * @property string $name
 */
class Channel
{
    //const ENTITY_NAME = 'salesChannel';

    const CHANNEL_KEY_TENDER = 'NET2';  // Ausschreibung
    const CHANNEL_KEY_FEE = 'NET3'; // Gebühren
    const CHANNEL_KEY_COMMISSION = 'NET4'; // Provisionen

    protected $fillable = ['key', 'name'];


    /**
     * Create a new Eloquent model instance.
     *
     * @param  array  $attributes
     * @return void
     */
    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            if (in_array($key, $this->fillable)) {
                $this->{$key} = $value;
            }
        }
    }


    // TODO: remove hardcode with better integration, fast solution for now
    //GET /salesChannel/activeSalesChannels
    public static function all()
    {
        return Collection::make([
            [
                'key' => static::CHANNEL_KEY_TENDER,
                'name' => 'Ausschreibung',
            ],
            [
                'key' => static::CHANNEL_KEY_FEE,
                'name' => 'Gebühren',
            ],
            [
                'key' => static::CHANNEL_KEY_COMMISSION,
                'name' => 'Provisionen',
            ]
        ])->mapInto(static::class);
    }


    public function __toString()
    {
        return $this->key;
    }
}
