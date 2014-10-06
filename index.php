<?php
/* 
 * @file
 * The main script
 */

function exception_handler($exception) {
  echo "Uncaught exception: " , $exception->getMessage(), "\n";
}
set_exception_handler('exception_handler');

function __autoload($class_name) {
  require_once 'classes/' . $class_name . '.class.php';
}

$board = new Board();

$board->openFirstBlankTile();

$player = new Player($board);
$board_drawer = new BoardDrawer($board);

$t = microtime(true);
$html = $board_drawer->draw();
$player->solve();
$html .= $board_drawer->draw();
$t = number_format((microtime(true) - $t)*1000, 2);

?>

<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en">

  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Minesweeper | A.I.</title>
    <meta http-equiv="Content-Style-Type" content="text/css" />
    <link type="text/css" rel="stylesheet" media="all" href="css/style.css" />
  </head>

  <body>
    <?php echo $t; ?><br /><br />
    <?php echo $html; ?>
  </body>

</html>