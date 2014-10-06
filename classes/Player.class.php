<?php
// $Id$

/**
 * @file
 * Minesweeper Player Class
 */

/**
 * This class is meant to simulate a minesweeper player.
 * It is able to read a minesweeper board (state) and solve the game.
 *
 * @author Vlad
 */
class Player {

  // class properties
  private $_board;
  private $_board_width;
  private $_board_height;
  private $_board_state;

  /**
   * Class constructor
   *
   * Reads the data from the board and populates self with basic data.
   */
  public function __construct(Board $board) {
    $this->_board = $board;
    $this->_board_width = $this->_board->getWidth();
    $this->_board_height = $this->_board->getHeight();
    $this->_board_state = $this->_board->getState();
  }


  /**
   * Computes and returns an array of relevant border value tiles.
   *
   * Each array element corresponds to a relevant open value tile. This means
   * that the tile (array element) has a value grater than 0 and is not fully
   * satisfied by the adiacent tiles that are already marked as mines.
   */
  private function _getBorderTileData() {
    $border_tiles = array();
    for ($i=0; $i<$this->_board_height; $i++) {
      for ($j=0; $j<$this->_board_width; $j++) {
        $border_tile = $this->_isBorderValue($i, $j);
        if ($border_tile) {
          $border_tiles []= array($i, $j, $this->_board_state[$i][$j][Board::VALUE], $border_tile);
        }
      }
    }
    return $border_tiles;
  }


  /**
   * Checks if the tile is an open border value tile and if so it returns an
   * array containing all adiacent closed tiles.
   *
   * The relevance test and the return of the array containing the neighbourng
   * tiles were combined into one method for performance optimization.
   */
  private function _isBorderValue($i, $j, $relevant = TRUE) {
    if (
      $this->_board_state[$i][$j][Board::VISIBILITY] === Board::CLOSED ||
      $this->_board_state[$i][$j][Board::VALUE] === Board::BLANK
    ) {
      return FALSE;
    }

    $relevance_test = FALSE;
    $all_neighbour_tiles = $this->_getNeighbourTiles($i, $j);
    $relevant_neighbouring_tiles = array();
    foreach ($all_neighbour_tiles as $tile) {
      list($i1, $j1, $value, $visibility, $flag) = $tile;
      if ($visibility === Board::CLOSED) { // && $flag === Board::NOT_FLAGGED
        $relevant_neighbouring_tiles[] = $tile;
        if ($flag === Board::NOT_FLAGGED) {
          $relevance_test = TRUE;
        }
      }
    }

    if (
      empty($relevant_neighbouring_tiles) ||
      ($relevant && !$relevance_test)
    ) {
      return FALSE;
    }
    else {
      return $relevant_neighbouring_tiles;
    }
  }


  /**
   * Returns an array of adiacent tiles or FALSE on failure.
   *
   * The reference tile is specified by the $i, $j parameters.
   */
  private function _getNeighbourTiles($i, $j) {
    if (isset($this->_board_state[$i][$j])) {
      $neighbours = array();
      for ($i1=$i-1; $i1<=$i+1; $i1++) {
        for ($j1=$j-1; $j1<=$j+1; $j1++) {
          if (isset($this->_board_state[$i1][$j1][Board::VALUE]) && ($i != $i1 || $j != $j1)) {
            $neighbours []= array(
              $i1, $j1,
              $this->_board_state[$i1][$j1][Board::VALUE],
              $this->_board_state[$i1][$j1][Board::VISIBILITY],
              $this->_board_state[$i1][$j1][Board::FLAG],
            );
          }
        }
      }
      return $neighbours;
    }
    return FALSE;
  }


  /**
   * Solves nT = n; equations
   *  - all neighbouring tiles are mines
   *
   * @param array $tile_data
   */
  private function _solveNTN() {
    $tiles = $this->_getBorderTileData();

    foreach ($tiles as $data) {
      if (count($data[3]) === $data[2]) {
        foreach ($data[3] as $d) {
          $this->_board_state[$d[0]][$d[1]][Board::FLAG] = Board::FLAGGED;
        }
      }
    }
    // Update the board.
    $this->_board->setState($this->_board_state);

    return $tiles;
  }


  /**
   * Solves nT = 0; equations and opens the relevant tiles.
   *
   * @param array $tile_data
   */
  private function _solveNT0($tiles) {
    $opened_new = FALSE; // tests if the player opened any tiles

    foreach ($tiles as $data) {
      $count = 0;
      $open = array();
      foreach ($data[3] as $d) {
        if ($this->_board_state[$d[0]][$d[1]][Board::FLAG] === Board::FLAGGED) {
          $count++; // count or
        }
        else {
          $open[] = $d; // prepare for open
        }
      }

      if ($count === $data[2] && !empty($open)) {
        foreach ($open as $d) {
          // Use the board to open tiles.
          $this->_board->open($d[0], $d[1]);
        }
        $opened_new = TRUE;
      }
    }
    // Read the new game state from the board.
    $this->_board_state = $this->_board->getState();

    return $opened_new;
  }


  /**
   * Computes and solves systems of equations
   */
  private function _solveEq() {
    $progress = FALSE;

    $tiles = $this->_getBorderTileData();
    $grouped_tiles = $this->_sortTileData($tiles);

    $results = array();
    $success = array();
    $temp_eqs = array();
    $possible_values = array();

    // for each group of tiles
    for ($k_gr=0; $k_gr<count($grouped_tiles); $k_gr++) {
      $eqs = array();
      list($eqs, $gt, $et) = $this->_buildEq($grouped_tiles[$k_gr]);

      $possible_values[$k_gr] = array();
      $success[$k_gr] = array();
      $vals = array();
      $t = microtime(true);

      // Find every possible solution for each equation while seeking validation
      // for all previously generated equations of the current group.
      foreach ($eqs as $k_eq => $eq) {
        $possible_values[$k_gr][$k_eq] = array();
        $success[$k_gr][$k_eq] = array();
        $nr_tiles = count($et[$k_eq]);
        $v_dec_max = pow(2, $nr_tiles);

        // Generate all possible values and test the equation.
        for ($v_dec=0; $v_dec<$v_dec_max; $v_dec++) {
          $v_dec_str = str_pad(decbin($v_dec), $nr_tiles, 0, STR_PAD_LEFT);
          $vals = str_split($v_dec_str);
          $eq_temp = str_replace($et[$k_eq], $vals, $eq);

          // Check if the values are a solution for the equation.
          eval('$pass = ' . $eq_temp . ';');
          if ($pass === TRUE) {
            // If the values are a solution for the equation, add them to
            // the $candidate_values array which contains all partial solutions.
            // The partial solutions will be checked against eachother later on.
            $candidate_values = array_combine($et[$k_eq], $vals);

            if ($k_eq == 0) {
              $possible_values[$k_gr][$k_eq][] = $candidate_values;
              continue;
            }

            $pass2 = TRUE;
            for ($k_eq2=0; $k_eq2<(count($possible_values[$k_gr])-1); $k_eq2++) {

              $pass_eq_test = FALSE;
              if (isset($possible_values[$k_gr][$k_eq2])) {
                for ($k_val=0; $k_val<count($possible_values[$k_gr][$k_eq2]); $k_val++) {

                  $pass_eq_val_test = TRUE;
                  foreach ($possible_values[$k_gr][$k_eq2][$k_val] as $k_tile => $pv) {
                    // Check the tile value in the current partial solution.
                    // If it doesn't match the value we are testing, skip to
                    // the next partial solution given by the current equation.
                    if (array_key_exists($k_tile, $candidate_values) && $candidate_values[$k_tile] != $pv) {
                      $pass_eq_val_test = FALSE;
                      break;
                    }
                  }

                  if ($pass_eq_val_test === TRUE) {
                    $pass_eq_test = TRUE;
                    break;
                  }

                }
              }

              // If the values we test for fail at least one equation, break.
              if ($pass_eq_test != TRUE) {
                $pass2 = FALSE;
                break;
              }
            }

            if ($pass2 === TRUE) {
              $possible_values[$k_gr][$k_eq][] = $candidate_values;
            }

          }
        }
      }

      $this->_removeParasiteGroupSolutions($possible_values[$k_gr]);

      $group_solution = $this->_solveGroup($possible_values[$k_gr]);
      $temp_progress = $this->_processGroupSolution($group_solution);
      $progress = $progress || $temp_progress;

    }

    // if no progress was made, open the tile that is the least likely
    // to be a mine and refresh the state of the board
    if (!$progress) {
      $probabilities = $this->_computeProbabilities($possible_values);
      if (!empty($probabilities)) {
	    $ak = array_keys($probabilities);
        list($i, $j) = explode('_', array_shift($ak));
        $this->_board->setState($this->_board_state);
        $this->_board->open($i, $j);
        $this->_board_state = $this->_board->getState();
        $progress = TRUE;
      }
    }

    return $progress;
  }


  /**
   * Remove parasite partial solutions from a group.
   *
   * During the generation of partial solutions the player checks their validity
   * against previous partial solutions only. This algorithm doesn't feed back
   * data to the previous partial solutions which may have become obsolete.
   *
   * This function will check each value of each partial solution against all
   * other partial solutions thus detecting and removing any parasite partial
   * solution from the system.
   *
   * @param array $pv
   */
  private function _removeParasiteGroupSolutions(&$pv) {
    static $count;
    $count++;
    $unset_check = FALSE;

    for ($k_eq=0; $k_eq<count($pv); $k_eq++) { // for each equation
      foreach ($pv[$k_eq] as $k_s => $pvs) { // for each solution (array)
        // Check solution against all other solutions
        $test = $this->_validateGroupSolution($pv, $k_eq, $k_s);
        if ($test == FALSE) {
          unset($pv[$k_eq][$k_s]);
          $unset_check = TRUE;
          //break;
        }
      }
    }

    if ($unset_check == TRUE) {
      $this->_removeParasiteGroupSolutions($pv);
    }
  }


  /**
   * This method does the heavy lifting for Player::_removeParasiteGroupSolutions().
   *
   * @param array $pv
   * The possible values array generated by Player::_solveEq().
   *
   * @param int $k_eq - equation key
   * @param int $k_s - (partial) solution key
   * 
   * @return boolean
   */
  private function _validateGroupSolution($pv, $k_eq, $k_s) {
    $valid = TRUE;
    for ($k_eq2=0; $k_eq2<count($pv); $k_eq2++) { // for each equation
      if ($k_eq2 != $k_eq) { // skip the check agains it's own
        $valid = FALSE;
        foreach ($pv[$k_eq2] as $k_s2 => $pvs2) { // for each solution (array)
          if (!isset($pv[$k_eq2][$k_s2])) {
            // $pv[$k_eq2][$k_s2] may have been unset
            $valid = TRUE;
            continue;
          }

          // every key in $k_s available in $k_s2 must be checked
          $temp_valid = TRUE;
          if (isset($pv[$k_eq][$k_s])) {
            foreach ($pv[$k_eq][$k_s] as $k_v => $v) {
              $temp_valid = TRUE;
              if (array_key_exists($k_v, $pv[$k_eq2][$k_s2]) && $pv[$k_eq2][$k_s2][$k_v] != $v) {
                $temp_valid = FALSE;
                break;
              }
            }
          }
          if ($temp_valid == TRUE) {
            // we found a partial solution that matches
            $valid = TRUE;
            break;
          }
          
        }
        if ($valid == FALSE) {
          // if the value fails for one equation there's no point in continuing
          return FALSE;
        }
      }
    }

    return $valid;
  }


  /**
   * Iterates through every possible solution and picks up the possible values
   * for each tile.
   *
   * @param array $pv
   * An array of possible values within a group
   *  - as generated witin Player::_solveEq().
   *
   * @return array
   * It returns an associative array with string keys representing the position
   * on the board - '$i_$j' - (coordinates concatenated with an underscore).
   * The values are indexed arrays containing 1s and 0s. Each 1 and 0 comes from
   * a possible solution to an equation - checked against all other equations.
   */
  private function _solveGroup($pv) {
    $solution = array();
    for ($k_eq=0; $k_eq<count($pv); $k_eq++) { // for each equation
      foreach ($pv[$k_eq] as $k_s => $pvs) { // for each solution (array)
        foreach ($pvs as $k_v => $v) {
          if (!isset($solution[$k_v])) {
            $solution[$k_v] = array($v);
          }
          else {
            $solution[$k_v][] = $v;
          }
        }
      }
    }
    return $solution;
  }

  /**
   * Opens and/or flags tiles based on the $solutions array.
   *
   * @param array $solution
   * Generated by Player::_solveGroup().
   *
   * @return bool
   * A progress indicator. If progress was made, it will return TRUE.
   * Progress is made when at least one tile is opened or flagged as a mine.
   */
  private function _processGroupSolution($solution) {
    $check_action_flag = FALSE;
    $check_action_open = FALSE;

    $open = array();
    foreach ($solution as $k => $s) {
      if (array_product($s) == 1) {
        // mark as mine
        list($i, $j) = explode('_', $k);
        $this->_board_state[$i][$j][Board::FLAG] = Board::FLAGGED;
        $check_action_flag = TRUE;
      }
      elseif (array_sum($s) == 0) {
        // expand
        list($i, $j) = explode('_', $k);
        $open[] = array($i, $j);
        $check_action_open = TRUE;
      }
    }

    if ($check_action_flag) {
      $this->_board->setState($this->_board_state);
    }
    if ($check_action_open) {
      for ($x=0; $x<count($open); $x++) {
        $this->_board->open($open[$x][0], $open[$x][1]);
      }
      $this->_board_state = $this->_board->getState();
    }

    return ($check_action_flag || $check_action_open);
  }


  /**
   * A quick and cheap way of figuring out which tile to open by comparing
   * probabilities.
   *
   * @param array $pv
   * All $possible_values generated by Player::_solveEq() - from all groups.
   *
   * @return array
   * A 2d associative array:
   *  - tile coordinates as a string => probability.
   */
  private function _computeProbabilities($pv) {
    $prob = array();
    $prob_eq = array();
    
    for ($k_gr=0; $k_gr<count($pv); $k_gr++) {
      for ($k_eq=0; $k_eq<count($pv[$k_gr]); $k_eq++) {
        // compute probabilities per eqation
        $k_peq = $k_gr . '_' . $k_eq;
        foreach ($pv[$k_gr][$k_eq] as $k_s => $sv) {
          //$p = array_sum($sv) / count;
          foreach ($sv as $k_v => $v) {
            // for each equation solution
            if (!isset($prob_eq[$k_v])) {
              $prob_eq[$k_v] = array();
            }

            if (!isset($prob_eq[$k_v][$k_peq])) {
              $prob_eq[$k_v][$k_peq] = array($v);
            }
            else {
              $prob_eq[$k_v][$k_peq][] = $v;
            }
          }
        }
      }
    }

    foreach ($prob_eq as $k_t => $v_teq) {
      foreach ($v_teq as $v) {
        $p = array_sum($v) / count($v);
        if (!isset($prob[$k_t])) {
          $prob[$k_t] = $p;
        }
        else {
          $prob[$k_t] = max($prob[$k_t], $p);
        }
      }
    }

    asort($prob);

    return $prob;
  }


  /**
   * Builds a system of equations given a given group of tiles
   *
   * @return array
   * An array containing 3 keys
   *  0 - an array of equations to be evaluated
   *  1 - a flat array of relevant closed tiles - the unknowns of the equations
   *  2 - an array of relevant closed tiles grouped by equation
   */
  private function _buildEq($group) {
    $eqs = array();
    $group_tiles = array();
    $eq_tiles = array();

    // for every tile in the group
    for ($x=0; $x<count($group); $x++) {
      $temp_eq = '';
      $temp_eq_tiles = array();
      $eq_val = $group[$x][2];

      // for every relevant neighbour (closed, unmarked tile)
      for ($y=0; $y<count($group[$x][3]); $y++) {
        if ($group[$x][3][$y][4] === Board::FLAGGED) {
          $eq_val--;
        }
        else {
          $temp_eq .= "{$group[$x][3][$y][0]}_{$group[$x][3][$y][1]} + ";
          $group_tiles[] = $group[$x][3][$y][0] . '_' . $group[$x][3][$y][1];
          $temp_eq_tiles[] = $group[$x][3][$y][0] . '_' . $group[$x][3][$y][1];
        }
      }
      if ($temp_eq) {
        $temp_eq = rtrim($temp_eq, ' +') . ' === ' . $eq_val;

        if (!in_array($temp_eq, $eqs)) {
          $eqs[] = $temp_eq;
          $eq_tiles[] = $temp_eq_tiles;
        }
      }
    }

    $group_tiles = array_values(array_unique($group_tiles));

    return array($eqs, $group_tiles, $eq_tiles);
  }

  /**
   * Creates groups of related open value tiles.
   *
   * @param array $tiles
   * As generated by $this->_getBorderTileData()
   *
   * @param int $max
   * Optional. Specifies the maxlength of a group.
   */
  private function _sortTileData($tiles, $max = 50) {
    $parts = array(array(array_shift($tiles)));

    while (count($tiles)) {
      $is_group = FALSE;

      for ($x=0; $x<count($parts); $x++) {
        if (count($parts[$x]) >= $max) {
          continue;
        }
        for ($y=0; $y<count($parts[$x]); $y++) {
          foreach ($tiles as $k => $tile) {
            $is_group = $this->_isGroup($tile, $parts[$x][$y]);

            if ($is_group) { //
              $parts[$x][] = $tile;
              unset($tiles[$k]);
              break 3;
            }
          }
        }
      }

      if (!$is_group) {
        $tile = array_shift($tiles);
        $parts[] = array($tile);
      }
    }

    return $parts;
  }


  /**
   * Checks if 2 data tiles are part of the same group. That is if they share
   * at least one common neighbouring tile.
   */
  private function _isGroup($t1, $t2) {
    for ($x=0; $x<count($t1[3]); $x++) {
      for ($y=0; $y<count($t2[3]); $y++) {
        if ($t1[3][$x] === $t2[3][$y] && $t1[3][$x][4] === Board::NOT_FLAGGED) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }


  /**
   * Solves the minesweeper game.
   */
  public function solve() {
    // delcare a board drawer and a html container (for display)

    // the display bit should realy be happening somewhere else...
    // maybe register a drawer with the board or simply keep it independent.
    // it will do for now since I'd like to display game progress easily.
    static $drawer, $html;
    if (!$drawer) {
      $drawer = new BoardDrawer($this->_board);
      $html = $drawer->draw();
    }

    // Do the leaset expensinve operations first,
    // until this logic becomes insufficient.
    $opened_new = TRUE;
    while ($opened_new) {
      $tiles = $this->_solveNTN();
      $opened_new = $this->_solveNT0($tiles);
      // uncomment the 'if' lines below to display intermediate steps
      //  if ($opened_new) {
      //    $html = $drawer->draw();
      //  }
    }

    // Check if the game is finished and if not, move on to the hardcore stuff.
    if ($this->_board->isGameFinished()) {
      $html = $drawer->draw();
      return $html;
    }
    
    $this->_solveEq(); // hard core stuff.

    if ($this->_board->isGameFinished()) {
      $html = $drawer->draw();
      return $html;
    }

    // If the game is not finished, rinse and repeat.
    $this->solve();
    //$html = $drawer->draw();
    
    return $html;
  }
} // The number of the beast! :p