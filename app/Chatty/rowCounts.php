<?php 

namespace App\Chatty;

class rowCounts {
    public $lolRowCounts = array(
        'create' => 0,
        'update' => 0,
        'delete' => 0
    );
    public $postRowCounts = array(
        'create' => 0,
        'update' => 0,
        'delete' => 0
    );
    public $threadRowCounts = array(
        'create' => 0,
        'update' => 0,
        'delete' => 0
    );
    public $eventRowCounts = array(
        'create' => 0,
        'update' => 0,
        'delete' => 0
    );

    public function arraysEmpty()
    {
        $result = 0;

        for($idx=0;$idx<3;$idx++) {
            $result += $lolRowCounts[$idx];
            $result += $postRowCounts[$idx];
            $result += $threadRowCounts[$idx];
            $result += $eventRowCounts[$idx];
        }

        if($result == 0) {
            return true;
        }

        return false;
    }
}