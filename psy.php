<?php

/*
 * PHP Shunting Yard Arlgorithm
 * by Niki Gorchilov <niki@gorchilov.com>
 *
 * Based on droptable's implementation available at
 * https://github.com/droptable/php-shunting-yard
 *
 * ---------------------------------------------------------------- 
 *
 * Permission is hereby granted, free of charge, to any person obtaining a 
 * copy of this software and associated documentation files (the "Software"), 
 * to deal in the Software without restriction, including without 
 * limitation the rights to use, copy, modify, merge, publish, distribute, 
 * sublicense, and/or sell copies of the Software, and to permit persons to 
 * whom the Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included 
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, 
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR 
 * PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS 
 * BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, 
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR 
 * OTHER DEALINGS IN THE SOFTWARE.
 *
 * <http://opensource.org/licenses/mit-license.php>
 *
 */
const
	T_NUMBER		= 1,	// nummer (integer / double)
	T_DEF			= 2,	// variable definition
	T_IDENT			= 3,	// constant
	T_FUNC			= 4,	// function
	T_POPEN			= 8,	// (
	T_PCLOSE		= 16,	// )
	T_COMMA			= 32,	// ,
	T_OPERATOR		= 64,	// operator (currently unused)
	T_PLUS			= 65,	// +
	T_MINUS			= 66,	// -
	T_MUL			= 67,	// * 
	T_DIV			= 68,	// /
	T_MOD			= 69,	// %
	T_POW			= 70,	// ^
	T_UNARY_PLUS	= 71,	// + positive sign
	T_UNARY_MINUS	= 72,	// - negative sign
	T_NOT			= 73;	// ! negative sign

static $token_type_names = array(
	T_NUMBER		=> 'T_NUMBER',
	T_DEF			=> 'T_DEF',
	T_IDENT			=> 'T_IDENT',
	T_FUNC			=> 'T_FUNC',
	T_POPEN			=> 'T_POPEN',
	T_PCLOSE		=> 'T_PCLOSE',
	T_COMMA			=> 'T_COMMA',
	T_OPERATOR		=> 'T_OPERATOR',
	T_PLUS			=> 'T_PLUS',
	T_MINUS			=> 'T_MINUS',
	T_MUL			=> 'T_MUL',
	T_DIV			=> 'T_DIV',
	T_MOD			=> 'T_MOD',
	T_POW			=> 'T_POW',
	T_UNARY_PLUS	=> 'T_UNARY_PLUS',
	T_UNARY_MINUS	=> 'T_UNARY_MINUS',
	T_NOT			=> 'T_NOT,'
);

class Token
{
	public $type, $value, $argc = 0;
  
	public function __construct($type, $value) {
		$this->type  = $type;
		$this->value = $value;
	}
}

class ShuntingYard {
	const
		ST_1 = 1, // waiting for operand or unary sign
		ST_2 = 2, // waiting for operator
		ERR_UNDEF = 'undefined symbol: `%s`',
		ERR_POPEN = 'missing or misplaced opening parenthesis',
		ERR_UNMATCH = 'unmatched parenthesis `%s` found',
		ERR_COMMA = 'commas are only allowed inside a function call',
		ERR_UNKNOWN = 'unknown type % for value `%s`';
		
	static $ops = array(
		T_PLUS			=> array('assoc' => 'l', 'preced' => 1),
		T_MINUS			=> array('assoc' => 'l', 'preced' => 1),
		T_UNARY_PLUS	=> array('assoc' => 'r', 'preced' => 4),
		T_UNARY_MINUS	=> array('assoc' => 'r', 'preced' => 4),
		T_MUL			=> array('assoc' => 'l', 'preced' => 2),
		T_DIV			=> array('assoc' => 'l', 'preced' => 2),
		T_MOD			=> array('assoc' => 'l', 'preced' => 2),
		T_POW			=> array('assoc' => 'r', 'preced' => 3),
		T_NOT			=> array('assoc' => 'r', 'preced' => 4),
	);

	private $tokenizer;
	private $state = self::ST_1;
	private $output = array();
	private $stack = array();
	private $funcs = array();

	function __construct($input) {
		if (is_object($input)) {
			$this->tokenizer = $input;
		} else {
			$this->tokenizer = new Tokenizer($input);
		}

		$token = $this->tokenizer->first();
		while ($token !== FALSE) {
			$this->handle($token);
			$token = $this->tokenizer->next();
		}

		while ($token = array_pop($this->stack)) {
			if (in_array($token->type, array(T_POPEN, T_PCLOSE))) {
				throw new Exception(sprintf(self::ERR_UNMATCH, $token->value));
			}
			$this->output[] = $token;
		}
		
		$this->reset();
	}
	
	function handle($token) {
		switch($token->type) {
			case T_NUMBER:
			case T_DEF:
			case T_IDENT:
				$this->output[] = $token;
				$this->state = self::ST_2;
				break;
			case T_FUNC:
				// push function to the stack
				$this->stack[] = $token;

				// handle the following opening parethesis
				$this->handle($this->tokenizer->next());

				// setup argument counter
				$token->argc = (($next = $this->tokenizer->peek()) and ($next->type !== T_PCLOSE)) ? 1 : 0;
				$this->funcs[] = $token;
				break;

				// count arguments number
				$argc = 0;
				if (($next = $this->tokenizer->peek()) and ($next->type !== T_PCLOSE)) {
					$argc = 1;
					while($t = $this->tokenizer->next()) {
						echo "tick: {$t->value}\n";
						$this->handle($t);
						if ($t->type === T_PCLOSE) {
							break;
						} elseif ($t->type === T_COMMA) {
							$argc++;
						}
					}
					$token->argc = $argc;
				}
				break;
			case T_COMMA:
				if (!($f = end($this->funcs))) {
					throw new Exception(sprintf(self::ERR_COMMA));
				} else {
					$f->argc++;
				}
				// pop out all stacked values and push in the output buffer utill reaching the opening parenthesis
				while(!empty($this->stack) and ($stacked_token = end($this->stack)) and ($stacked_token->type !== T_POPEN)) {
					$this->output[] = array_pop($this->stack);
				}
				if (isset($stacked_token) and ($stacked_token->type !== T_POPEN)) {
					throw new Exception(sprintf(self::ERR_POPEN));
				}
				break;
			case T_PLUS:
			case T_MINUS:
				if ($this->state === self::ST_1) {
					// set unary plus or minus
					$token->type = ($token->type === T_PLUS) ? T_UNARY_PLUS : T_UNARY_MINUS;
				}
				// no break
			case T_MUL:
			case T_DIV:
			case T_MOD:
			case T_POW:
			case T_NOT:
				while(!empty($this->stack) and ($stacked_token = end($this->stack))) {
					if (
						!in_array($stacked_token->type, array_keys(self::$ops)) or
						!(
							(
								self::$ops[$token->type]['assoc'] === 1 and
								(
									self::$ops[$token->type]['preced'] <= self::$ops[$stacked_token->type]['preced']
								)
							) or (
								self::$ops[$token->type]['preced'] < self::$ops[$stacked_token->type]['preced']
							)
						)
					) {
						// exit from loop
						break;
					}
					// pop the last stacked value into the output buffer
					$this->output[] = array_pop($this->stack);
				}
				$this->stack[] = $token;
				$this->state = self::ST_1;
				break;
			case T_POPEN:
				// If the token is a left parenthesis, push it into the stack.
				$this->stack[] = $token;
				$this->state = self::ST_1;
				break;
			case T_PCLOSE:
				$output = $this->output;
				$stack = $this->stack;
				while(($stacked_token = array_pop($this->stack)) and ($stacked_token->type !== T_POPEN)) {
					$this->output[] = $stacked_token;
				}
				if (isset($stacked_token) and ($stacked_token->type !== T_POPEN)) {
					throw new Exception(sprintf(self::ERR_POPEN));
				}
				if (($next = end($this->stack)) and ($next->type === T_FUNC)) {
					// pop the last stacked function name into the output buffer
					$this->output[] = array_pop($this->stack);
					// remove from functions list
					array_pop($this->funcs);
				}
				$this->state = self::ST_2;
				break;
			default:
				throw new Exception(sprintf(self::ERR_UNKNOWN, $token->type, $token->value));
		}
	}

	public function dump() {
		$this->reset();
		echo "\n";
		echo str_pad("TOKEN TYPE", 20) . str_pad('TOKEN VALUE', 20) . "ARGC\n";
		echo str_pad("----------", 20) . str_pad('-----------', 20) . "----\n";
		foreach($this->output as $token) {
			echo str_pad($GLOBALS['token_type_names'][$token->type], 20) . str_pad($token->value, 20) .  ($token->type === T_FUNC ? $token->argc : 'n/a') . "\n";
		}
		echo "\n";
		$this->reset();
	}	

	public function reset() { return reset($this->output); }
	public function first() { $this->reset(); return $this->curr(); }
	public function curr() { return current($this->output); }
	public function next() { return next($this->output); }
	public function prev() { return prev($this->output); }
	public function key() { return key($this->output); }
}

class Tokenizer {

	const
		REGEX = '/^([!,\+\-\*\/\^%\(\)]|\d*\.\d+|\d+\.\d*|\d+|\$[a-z_A-Z0-9:]+|[a-z_A-Z]+[a-z_A-Z0-9]*|[ \t]+)/',
		ERR_MATCH = 'syntax error near: `%s`',
		ERR_EMPTY = 'invalid expression: `%s`';
	static $token_types = array(
		'!' => T_NOT,
		'+' => T_PLUS,
		'-' => T_MINUS,
		'*' => T_MUL,
		'/' => T_DIV,
		'%' => T_MOD,
		'^' => T_POW,
		'(' => T_POPEN,
		')' => T_PCLOSE,
		',' => T_COMMA,
	);
	public $tokens = array();

	function __construct($expression) {

		$prev = NULL;

		while(($expression = trim($expression)) !== '') {
			if (!preg_match(self::REGEX, $expression, $matches)) {
				throw new Exception(sprintf(self::ERR_MATCH, substr($expression, 0, 10)));
			}

			$match = $matches[1];

			if (empty($match) and $match !== '0') {
				throw new Exception(sprintf(self::ERR_EMPTY, substr($expression, 0, 10)));
			}

			$expression = substr($expression, strlen($match));
			
			if (($value = trim($match)) === '') {
				continue;
			}

			if (array_key_exists($value, self::$token_types)) {
				$type = self::$token_types[$value];
				if (($type === T_POPEN) and ($prev->type === T_IDENT)) {
					$prev->type = T_FUNC;
				}
			} elseif (is_numeric($value)) {
				$type  = T_NUMBER;
				$value = (float) $value;
			} elseif ($value[0] === '$') {
				$type = T_DEF;
				$value = substr($value, 1);
			} else {
				$type = T_IDENT;
			}

			$this->tokens[] = $prev = new Token($type, $value);
		}
	}

	public function reset() { return reset($this->tokens); }
	public function first() { $this->reset(); return $this->curr(); }
	public function curr() { return current($this->tokens); }
	public function next() { return next($this->tokens); }
	public function prev() { return prev($this->tokens); }
	public function key() { return key($this->tokens); }

	public function peek() {
		$v = next($this->tokens);
		prev($this->tokens);
		return $v;
	}

	public function dump() {
		$this->reset();
		echo str_pad("\nTOKEN TYPE", 20) . "TOKEN VALUE\n";
		echo str_pad('----------', 20) . "-----------\n";
		foreach($this->tokens as $token) {
			echo str_pad($GLOBALS['token_type_names'][$token->type], 20) . "{$token->value}\n";
		}
		echo "\n";
		$this->reset();
	}
}

?>