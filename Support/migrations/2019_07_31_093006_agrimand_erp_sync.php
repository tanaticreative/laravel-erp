<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class TanErpSync extends Migration
{
    const T_ERP_SYNC_STATE = 'erp_sync_state';

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        /* * @property array $payload TODO: MOVE TO REQUEST LOG
 * @property array $response TODO: MOVE TO REQUEST LOG
 * @property int $status TODO: MOVE TO REQUEST LOG
 * @property int $direction TODO: MOVE TO REQUEST LOG*/

        Schema::create(self::T_ERP_SYNC_STATE, function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('entity_id')->index();
            $table->string('entity_type_id')->index();
            $table->unsignedInteger('target_id')->nullable()->index();
            $table->unsignedInteger('target_type_id')->nullable()->index();
            $table->unsignedInteger('version')->default(0);
            //$table->json('payload')->nullable();
            //$table->json('response')->nullable();
            //$table->unsignedTinyInteger('status')->index();
            //$table->unsignedTinyInteger('direction');
            $table->timestamps();

            $table->index(['target_id', 'target_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists(self::T_ERP_SYNC_STATE);
    }
}
