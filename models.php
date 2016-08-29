<?php
/*

Models for Slack Tic Tac Toe

*/

require_once 'constants.php';


class BoardPrinter {
    # Singleton for converting board data to viewable text.

    public $new_line = "\xA";

    public $spacer = "\x20\x20";
    public $empty_mark = "\x20";

    public $line_spacer = "--";
    public $line_mark = "-";

    public $board;

    public function rowDelimiter() {
        # Formats border within a row.

        return $this->spacer . '|' . $this->spacer;
    }

    public function spacerRow() {
        # Formats margin around player's mark, by row.

        return $this->spacer . $this->empty_mark . $this->spacer . $this->rowDelimiter() . $this->spacer . $this->empty_mark . $this->spacer . $this->rowDelimiter() . $this->spacer . $this->empty_mark . $this->space . $this->new_line;
    }

    public function lineDelimiter() {
        # Formats intersection of board.

        return $this->line_spacer . '+' . $this->line_spacer;
    }

    public function lineRow() {
        # Formats horizontal lines of board.

        return $this->line_spacer . $this->line_mark . $this->line_spacer . $this->lineDelimiter() . $this->line_spacer . $this->line_mark . $this->line_spacer . $this->lineDelimiter() . $this->line_spacer . $this->line_mark . $this->line_spacer . $this->new_line;

    }

    public function gameRow($row_idx) {
        # Formats players' marks on board.

        $row_start = $row_idx * 3;
        return $this->spacer . $this->board[$row_start] . $this->spacer . $this->rowDelimiter() . $this->spacer . $this->board[$row_start + 1] . $this->spacer . $this->rowDelimiter() . $this->spacer . $this->board[$row_start + 2] . $this->space . $this->new_line;
    }

    public function getBoard() {
        # Convert board data to a text string.

        return $this->spacerRow() . $this->gameRow(0) . $this->spacerRow() . $this->lineRow() . $this->spacerRow() . $this->gameRow(1) . $this->spacerRow() . $this->lineRow() . $this->spacerRow() . $this->gameRow(2) . $this->spacerRow();
    }

} # end BoardPrinter


class TTTGame {
    private $EMPTY_BOARD = array(NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

    private $objectname = "TTTGame";
    public $id = "NONE";
    public $board;

    public $players = array();
    public $current_player_idx = 0;
    public $active = False;


    # DATA PERSISTENCE (mysql)
    
    private function dataGet() {
        if (!$this->id) return NULL;
        global $db;
        $statement = $db->prepare("SELECT t3g.game FROM ttt_game t3g WHERE t3g.channel_id = :channel_id");
        $statement->execute(array(':channel_id' => $this->id));
        $row = $statement->fetch();
        $std_array = json_decode($row[0]);

        if (is_object($std_array)) {
            foreach ($std_array as $column => $column_val) $this->$column = $column_val;
            return $this;
        } else return False;
    }
    
    private function dataPut() {
        if (!$this->id) return NULL;
        $game_json = json_encode($this);
        global $db;
        $statement = $db->prepare("INSERT INTO ttt_game (channel_id, game) VALUES (:channel_id, :game_json) ON DUPLICATE KEY UPDATE game = :game_json");
        $statement->execute(array(':channel_id' => $this->id, ':game_json' => $game_json));
        return $statement->rowCount();
    }

    
    # "SERVICE METHODS"
    
    public function getGame($channel_id) {
        # Retrieves by channel_id a en existing game or creates a new one.

        $this->id = $channel_id;
        $previous_game = $this->dataGet($channel_id);
        if ($previous_game) {
            return $previous_game;
        } else {
            $this->initBoard()->saveGame();
            return $this;
        }
    }

    public function saveGame() {
        if (!$this->id) {
            return False;
        }
        return $this->dataPut();
    }

    public function initBoard() {
        $this->board = $this->EMPTY_BOARD;
        return $this;
    }

    public function startGame($player1, $player2) {
        # Starts game by setting players and wiping board clean.

        $this->players = array($player1, $player2);
        $this->active = True;
        $this->initBoard();
        return True;
    }
    
    public function playerTakesTurn($position) {
        # Accepts a position mark (int from 0 - 8) to mark player's selection.  Returns True on success or False for bad position.
        # Should only be called after confirming that userIsCurrentPlayer().

        if (($position >= 0) && ($position <= 8) && !$this->board[$position]) {
            $this->board[$position] = $this->players[$this->current_player_idx]->user_name;
            $this->checkForWinOrDraw();
            $this->checkForStalemate();            
            $this->current_player_idx = !$this->current_player_idx;
            $this->saveGame();
            return True;
        }
        return False;
    }

    public function currentPlayer() {
        return $this->players[$this->current_player_idx];
    }

    public function boardIsEmpty() {
       if (count(array_filter($this->board)) == 0) return True;
       else return False;
    }

    public function getPrintableBoard() {
        # For outputting the board, which holds position by player's name, to an array of X's, O's and nulls for text output.

        $printable_board = array();
        foreach ($this->board as $position) {
            $printable_board[] = $this->spaceOrMark($this->playerNameToMark($position));
        }
        return $printable_board;
    }

    public function getInstructionBoard() {
        return array(1,2,3,4,5,6,7,8,9);
    }

    private function spaceOrMark($board_position) {
        # Helper function for getPrintableBoard.  Converts board model to string marks (or empty string if no marks on that position).

        if (!$board_position) {
            return "\x20";
        } else {
            return $board_position;
        }
    }

    public function getPlayerByName($user_name) {
        foreach ($this->players as $player) {
            if ($player->user_name == $user_name) return $player;
        }
    }

    public function playerNameToMark($user_name) {
        if (!$user_name) return NULL;
        return $this->getPlayerByName($user_name)->mark;
    }


    # STATUS, PROMPTS, BOARD OUTPUT

    public function getStatus() {
        # Returns status string that prompts users to take their turn or begin a new game.

        if ($this->active) {
            return "It's " . $this->players[$this->current_player_idx]->user_name . "'s turn!";
        } elseif ($this->boardIsEmpty()) {
            return NEW_GAME_PROMPT . PLAYER_TURN_INSTRUCTIONS;
        } else {
            $text_out = '';
            $winner = $this->getWinner();
            if ($winner) $text_out .= $winner->user_name . " wins!";
            else $text_out .= "Cat's game."; # this is a brittle assumption that no winner, and a not empty board means a draw
            $text_out .= NEW_GAME_PROMPT;
            return $text_out;
        }
    }

    public function getWhoChallengedWhom() {
        # Reports the opening user invitation.

        foreach ($this->players as $player) {
            if ($player->mark === 'X') $challenger = $player;
            if ($player->mark === 'O') $challengee = $player;
        }
        if ($challenger && $challengee) {
            return $challenger->user_name . " has challenged " . $challengee->user_name . "!";
        }
    }

    public function getIntro() {
        # Returns an intro string for welcoming users or describing an ongoing game.

        $text_out = '';
        if (count($this->players)) {
            $text_out = ($this->boardIsEmpty() ? $this->getWhoChallengedWhom() : $this->players[0]->user_name . " vs " . $this->players[1]->user_name . "!");
        } else {
            $text_out = "Welcome to Tic Tac Toe!";
        }
        return  $text_out . "\xA\xA";
    }

    public function userIsCurrentPlayer($user_name) {
        if ($this->players[$this->current_player_idx]->user_name === $user_name) return True;
        return False;
    }


    # GAME LOGIC

    public function winsByRow($user_name, $board=NULL) {
        $board = ($board ? $board : $this->board);
        for ($i = 0; $i < 3; $i+=3) {
            if ($board[$i] == $user_name && $board[$i+1] == $user_name && $board[$i+2] == $user_name) return True; 
        }
        return False;
    }

    public function winsByColumn($user_name, $board=NULL) {
        $board = ($board ? $board : $this->board);
        for ($i= 0; $i< 3; $i++) {
            if ($board[$i] == $user_name && $board[$i+3] == $user_name && $board[$i+6] == $user_name) return True;
        }
        return False;
    }

    public function winsByDiagonal($user_name, $board=NULL) {
        $board = ($board ? $board : $this->board);
        if ($board[4] == $user_name) {
            if ($board[0] == $user_name && $board[8] == $user_name) return True;
            if ($board[2] == $user_name && $board[6] == $user_name) return True;
        }
        return False;
    }

    public function playerWins($player, $board=NULL) {
        $board = ($board ? $board : $this->board);
        if ($this->winsByRow($player->user_name, $board) || $this->winsByColumn($player->user_name, $board) || $this->winsByDiagonal($player->user_name, $board)) {
            return True;
        }
        return False;
    }

    public function checkForWinOrDraw() {
        if ($this->boardIsEmpty()) return False;
        if ($this->getWinner()) $this->active = False;
        if (count(array_filter($this->board)) == 9) $this->active = False;
    }

    public function getWinner() {
        foreach ($this->players as $player) {
            if ($this->playerWins($player)) {
                $this->active = False;
                return $player;
            }
        }
        return NULL;
    }

    private function fillEmptyPositionsWithPlayerMarks($user_name) {
        $hypothetical_board = array();
        foreach ($this->board as $position) $hypothetical_board[] = ($position ? $position : $user_name);
        return $hypothetical_board;
    }

    private function playerCanWin($player) {
        return $this->playerWins($player, $this->fillEmptyPositionsWithPlayerMarks($player->user_name));
    }

    private function checkForStalemate() {
        foreach ($this->players as $player) {
            if ($this->playerCanWin($player)) return False;
        }

        # cat's game!
        $this->active = False;
        return True;
    }

} # end TTTGame


class Player {
    public $user_name;
    public $mark;

    public function __construct ($user_name, $mark) {
        $this->user_name = $user_name;
        $this->mark = $mark;
    }
}


class SlackResponse {
    public $response_type = "ephemeral";
    public $text = '';
    public $attachments;

}
?>