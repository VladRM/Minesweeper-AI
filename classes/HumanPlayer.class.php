<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class HumanPlayer {

  private $_board;

  public function __construct($state, $json = FALSE) {
    if ($json) {
      $state = json_decode($state);
    }
    $this->_board = Board::getInstanceByState($state);
  }

}

?>
