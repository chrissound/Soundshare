<?php

use Phinx\Migration\AbstractMigration;

class ImportOldSoundshare extends AbstractMigration
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
        //$this->dropTable('admins');
        //$this->dropTable('collections');

        $commentTable= $this->table('comments');
        $commentTable->rename('comment');
        $commentTable->save();

        $this->execute("
            ALTER TABLE `comment`
            CHANGE `content` `content` text COLLATE 'utf8mb4_unicode_ci'");
        $commentTable->addColumn('timestamp', 'integer');
        $commentTable->update();

        $comments = $this->query('SELECT * FROM comment');
        foreach ($comments as $comment)
        {
            // grr mysql raw query....
            $this->execute("
                UPDATE comment
                SET timestamp=". strtotime($comment['date']) . "
                WHERE id = " . $comment['id']);
        }
        $commentTable->getAdapter()->dropColumn('comment', 'date');
        $commentTable->save();

        $this->dropTable('collections');
        $this->dropTable('admins');
        $this->dropTable('unvalidated_sounds');

        // sounds
        //
        $soundTable= $this->table('sounds');
        $soundTable->rename('sound');
        $soundTable->renameColumn('name', 'title');
        $soundTable->save();

        $soundTable->getAdapter()->dropColumn('sound', 'total_votes');
        $soundTable->getAdapter()->dropColumn('sound', 'vote');
        $soundTable->getAdapter()->dropColumn('sound', 'hq_flag');
        $soundTable->addColumn('created_timestamp', 'integer');
        $soundTable->addColumn('present_files', 'integer', array('limit' => 1));
        $soundTable->addColumn('approve', 'integer', array('limit' => 1));
        $soundTable->update();
        $sounds = $this->query('SELECT * FROM sound');
        foreach ($sounds as $sound)
        {
            // grr mysql raw query....
            $this->execute("
                UPDATE sound 
                SET created_timestamp=". strtotime($sound['date']) . ",
                present_files = 1,
                approve = 1
                WHERE id = " . $sound['id']);
        }
        $soundTable->getAdapter()->dropColumn('sound', 'date');

        // users activation
        $activationTable = $this->table('activation');
        $activationTable
            ->addColumn('code', 'string')
            ->addColumn('activated', 'integer')
            ->addColumn('user_id', 'integer')
            ->addIndex(array('id'), array('unique' => true))
            ->save();

        $userTable = $this->table('users');
        $userTable->rename('user');
        $userTable->renameColumn('username', 'alias');
        $userTable->save();

        $users = $this->query('SELECT * FROM user');
        foreach ($users as $user)
        {
            // grr mysql raw query....
            $this->execute("INSERT INTO activation (code, activated, user_id) 
                VALUES ('', 1, ". $user['id'] .")");
        }

    }

    /**
     * Migrate Down.
     */
    public function down()
    {

    }
}
