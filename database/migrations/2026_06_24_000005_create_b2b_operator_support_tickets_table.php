<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BOperatorSupportTicketsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_operator_support_tickets')) {
            return;
        }

        Schema::create('b2b_operator_support_tickets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('operator_id')->index();
            $table->string('ticket_uid', 80)->unique();
            $table->string('subject', 160);
            $table->string('status', 30)->default('open')->index();
            $table->string('priority', 20)->default('normal')->index();
            $table->string('category', 80)->nullable()->index();
            $table->string('external_reference', 120)->nullable()->index();
            $table->json('context')->nullable();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['operator_id', 'status', 'last_message_at'], 'b2b_ost_operator_status_last_idx');
            $table->index(['operator_id', 'priority', 'created_at'], 'b2b_ost_operator_priority_created_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_operator_support_tickets');
    }
}
