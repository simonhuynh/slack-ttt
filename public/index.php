<?php
/*
Slack Tic Tac Toe Game (Controller)

REQUIREMENTS
Users can create a new game in any Slack channel by challenging another user (using their username).
A channel can have at most one game being played at a time.
Anyone in the channel can run a command to display the current board and list whose turn it is.
Users can specify their next move, which also publicly displays the board in the channel after the move with a reminder of whose turn it is.
Only the user whose turn it is can make the next move.
When a turn is taken that ends the game, the response indicates this along with who won.


QUESTIONS
Is username unique within a channel? If no, what is the expected behavior?
Can a user play themeselves? If so, expected behavior?
*/

require_once '../require.php';

# MINIMAL VALIDATION
if ($_POST['token'] !== $TOKEN) {    
    header('HTTP/1.0 404 Not Found');
    print "404 Not Found";
    exit();
}

header('Content-type: application/json');

$user_params = explode(' ', $_POST['text']);
$username = $_POST['user_name'];

$ttt = new TTTGame();
$ttt = $ttt->getGame($_POST['channel_id']);

$board_printer = new BoardPrinter();

$response = new SlackResponse();

if ($user_params[0] == HELP) {
    $board_printer->board = $ttt->getInstructionBoard();
    $response->text = "```\xA" . $board_printer->getBoard() . PLAYER_TURN_INSTRUCTIONS . NEW_GAME_PROMPT;
    print json_encode($response);
    exit();
} elseif (!$ttt->active) {
    # user can start a new game
    if ($user_params[0] == VS_COMMAND && $user_params[1]) {
        $player1 = new Player($user_params[1], 'O');
        $player2 = new Player($_POST['user_name'], 'X');
        $ttt->startGame($player1, $player2);
        $ttt->saveGame();
        $response->response_type = 'in_channel';
    }
} elseif ($ttt->userIsCurrentPlayer($username)) {
    # user can take turn
    $position = (int) $user_params[0];    
    if ($position) {
        $position = (int) $position;
        $success = $ttt->playerTakesTurn($position - 1); # so as not to subject users to our array zero-start index
        if ($success) $response->response_type = 'in_channel';
    }
} 

$board_printer->board = (($ttt->boardIsEmpty() && !$ttt->active) ? $ttt->getInstructionBoard() : $ttt->getPrintableBoard());
$response_text = "```\xA" . $ttt->getIntro() . $board_printer->getBoard() . "\xA" . $ttt->getStatus() . "```";

# $response_text .= "post token is:" . $_POST['token'];

$response->text = $response_text;

print json_encode($response);
?>
