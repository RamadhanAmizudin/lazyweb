<?php

class AumWAF {

	var $string = "";
	var $detected = false;

	function initialize() {
		return $this;
	}

	function prepare(...$args) {
		foreach($args as $arg) {
			$this->string .= json_encode($arg) . "|";
		}
		return $this;
	}

	function detect() {
		foreach( $this->signature() as $sig ) {
			if( stripos($this->string, $sig) !== false ) {
				$this->detected = true;
			}
		}
		return $this;
	}

	function block() {
		if( $this->detected ) {
			die("Your Request Has Been Blocked by AumWAF &copy; 2018.");
		}
	}

	function signature() {
		return [
			'sqlmap',
			'acunetix',
			'nessus',
			'bot',
			'scan',
			'zap',
			'parros',
			'injector'
		];
	}
}
