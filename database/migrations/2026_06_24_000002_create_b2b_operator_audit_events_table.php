<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BOperatorAuditEventsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_operator_audit_events')) {
            return;
        }

        Schema::create('b2b_operator_audit_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('operator_id')->nullable()->index();
            $table->string('event_type', 100)->index();
            $table->string('subject_type', 80)->nullable();
            $table->string('subject_id', 191)->nullable();
            $table->string('actor', 100);
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'event_type']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_operator_audit_events');
    }
}
