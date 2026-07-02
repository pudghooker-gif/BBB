<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateB2BOperatorSupportTicketMessagesTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('b2b_operator_support_ticket_messages')) {
            return;
        }

        Schema::create('b2b_operator_support_ticket_messages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('ticket_id')->index();
            $table->unsignedBigInteger('operator_id')->index();
            $table->string('actor', 100);
            $table->string('source', 40)->default('operator_portal')->index();
            $table->longText('message');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['ticket_id', 'created_at'], 'b2b_ostm_ticket_created_idx');
            $table->index(['operator_id', 'created_at'], 'b2b_ostm_operator_created_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('b2b_operator_support_ticket_messages');
    }
}
