<?php

namespace ether\mux\migrations;

use craft\db\Migration;
use craft\db\Table;
use ether\mux\Mux;

class Install extends Migration
{

	public function safeUp ()
	{
		$this->createTable(Mux::MUX_ASSETS_TABLE, [
			'assetId'    => $this->integer(11)->notNull(),
			'muxId'      => $this->string()->notNull(),
			'playbackId' => $this->string()->defaultValue(null),
			'primary key (assetId, muxId)',
		]);

		$this->addForeignKey(
			null,
			Mux::MUX_ASSETS_TABLE, 'assetId',
			Table::ASSETS, 'id',
			'CASCADE'
		);
	}

	public function safeDown ()
	{
		$this->dropTableIfExists(Mux::MUX_ASSETS_TABLE);
	}

}