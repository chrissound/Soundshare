<?php

use Phinx\Migration\AbstractMigration;

class ProfileAndCoverImages extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-change-method
     *
     * Uncomment this method if you would like to use it.
     *
    public function change()
    {
    }
    */
    
    /**
     * Migrate Up.
     */
    public function up()
    {
        $imageTypeTable = $this->table('image_type');
        $imageTypeTable->save();
        $imageTypeTable->addColumn('type', 'string');
        $imageTypeTable->save();

        $this->execute("
            INSERT INTO image_type (type)
            VALUES('profile_picture')");
        $this->execute("
            INSERT INTO image_type (type)
            VALUES('cover_picture')");

        
        $imageTable = $this->table('image');
        $imageTable->save();
        $imageTable->addColumn('user_id', 'integer');
        $imageTable->addColumn('image_type_id', 'integer');
        $imageTable->save();
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        $this->dropTable('image');
        $this->dropTable('image_type');
    }
}
