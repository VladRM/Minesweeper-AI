<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of board_printer
 *
 * @author Vlad
 */
class BoardDrawer {

  // class properties
  private $_board;
  private $_board_width;
  private $_board_height;
  private $_board_state;

  /**
   * Class constructor
   *
   * @param Board $board
   */
  public function __construct(Board $board) {
    $this->_board = $board;
    $this->_board_width = $board->getWidth();
    $this->_board_height = $board->getHeight();
    $this->_board_state = $board->getState();
  }

  /**
   * Returns an HTML table representation of the minesweeper board.
   *
   * @return string
   */
  public function draw() {
    $this->_board_state = $this->_board->getState();
    $html = '<table class="ms-board">';
    $html .= '<tr><td class="idx">&nbsp;</td>';
    for ($j=0; $j<$this->_board_width; $j++) {
      $html .= '<td class="idx">'. $j .'</td>';
    }
    $html .= '</tr>';

    for ($i=0; $i<$this->_board_height; $i++) {
      $html .= '<tr>';
      $html .= '<td class="idx">'. $i .'</td>';
      for ($j=0; $j<$this->_board_width; $j++) {
        if ($this->_board_state[$i][$j][Board::VISIBILITY] === Board::CLOSED) {
          if ($this->_board_state[$i][$j][Board::FLAG] === Board::FLAGGED) {
            $class = 'ms-tile-flagged';
          }
          else {
            $class = 'ms-tile-closed';
          }
          $value = '&nbsp;';
        }
        else {
          $value = str_replace('-1', 'x', $this->_board_state[$i][$j][Board::VALUE]);
          $class = 'ms-tile-'. $value;
        }
        $value = str_replace('0', '&nbsp;', $value);
        $html .= '<td class="'. $class .' pos-'. $i .'-'. $j .'">'. $value .'</td>';
      }
      $html .= '</tr>';
    }
    $html .= '</table>';

    $status_msg = 'Game status: '. ($this->_board->isGameFinished() ? 'Finished' : 'Open');
    if ($this->_board->isGameFinished()) {
      $status_msg .=  ' - Resolution: '. ($this->_board->isGameSolved() ? 'Solved' : 'Failed');
    }
    $html_status = '<div class="status">'. $status_msg .'</div>';

    $html = '<div>'. $html_status . $html .'</div>';

    return $html;
  }
}
?>
