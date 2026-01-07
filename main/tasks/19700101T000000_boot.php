<?php

use Core\Context;

/**
 * 19700101T000000_boot.php
 *
 * @version 1.0.0
 * @return boolean `true` if task is recurring 
 */

return function (Context $context) {
    $db = $context->db();
    $db->buildSchema(true);
    return true;
};
