<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BOperatorHealthEventsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_operator_health_events')) {
            return;
        }

        Schema::create('b2b_operator_health_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('operator_id')->index();
            $table->string('event_type')->index();
            $table->string('status')->index();
            $table->unsignedInteger('failure_count')->default(0);
            $table->text('message')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->foreign('operator_id')->references('id')->on('b2b_operators')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_operator_health_events');
    }
}
