<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
Copyright (C) 2005 - 2021 Packet Tide, LLC.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
PACKET TIDE, LLC BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Except as contained in this notice, the name of Packet Tide, LLC shall not be
used in advertising or otherwise to promote the sale, use or other dealings
in this Software without prior written authorization from Packet Tide, LLC.
*/


/**
 *  Cron Class
 *
 * @package			ExpressionEngine
 * @category		Plugin
 * @author			Packet Tide
 * @copyright		Copyright (C) 2005 - 2021 Packet Tide, LLC
 * @link			https://github.com/EllisLab/Cron
 */

class Cron {

	public $return_data = '';
	public $cache_name  = 'cron';

	public $minute      = '0';
	public $hour        = '0';
	public $day         = '*';
	public $month       = '*';
	public $weekday     = '*';
	public $year        = '*';

	public $check       = 0;
	public $now         = array();
	public $last        = array();
	public $crontab     = array();
	public $id          = '';
	public $cache_file  = '';
	public $params;

	/**
	 * Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	function __construct()
	{
		$this->params = array('minute', 'hour', 'day', 'month', 'weekday', 'year');

		$this->check = time();
		list($this->now['minute'], $this->now['hour'], $this->now['day'], $this->now['month'], $this->now['weekday'], $this->now['year']) = explode(',', strftime("%M,%H,%d,%m,%w,%Y", $this->check));

		$this->id			= md5(ee()->TMPL->tagproper);
		$this->cache_file	= APPPATH.'cache/'.$this->cache_name.'/'.$this->id;

		if ($this->parse_cron() === TRUE)
		{
			ee()->TMPL->log_item("Parse Cron job");

			$this->write_cache();

			if (ee()->TMPL->fetch_param('module') !== FALSE)
			{
				$this->return_data = $this->class_handler(ee()->TMPL->fetch_param('module'), 'modules');
			}
			elseif(ee()->TMPL->fetch_param('plugin') !== FALSE)
			{
				$this->return_data = $this->class_handler(ee()->TMPL->fetch_param('plugin'), 'plugins');
			}
			else
			{
				$this->return_data = ee()->TMPL->tagdata;
			}
		}
		else
		{
			ee()->TMPL->log_item("Cron cache not yet expired");
		}
	}

	// --------------------------------------------------------------------

	/**
	 * class_handler
	 *
	 * Function description
	 *
	 * @access   public
	 * @param	string
	 * @param	string
	 * @return   string
	 */
	function class_handler($str, $type='plugins')
	{
		$data = '';

		// Remove any foolish tags added in by foolish mortals

		$x = explode(':', trim(str_replace(array('{exp:','}'), '', $str)));

		if (in_array($x['0'], ee()->TMPL->{$type}))
		{
			if ( ! class_exists($x['0']))
			{
				if ($type == 'modules')
				{
					if (in_array($x['0'], ee()->core->native_modules))
					{
						require_once PATH_MOD.$x['0'].'/mod.'.$x['0'].'.php' ;
					}
					else
					{
						require_once PATH_THIRD.$x['0'].'/mod.'.$x['0'].'.php' ;
					}
				}
				else
				{
					if (in_array($x['0'], ee()->core->native_plugins))
					{
						require_once PATH_PI.'pi.'.$x['0'].'.php';
					}
					else
					{
						require_once PATH_THIRD.$x['0'].'/'.'pi.'.$x['0'].'.php';
					}
				}
			}

			$class_name = ucfirst($x['0']);
			$meth_name  = (isset($x['1']) && trim($x['1']) != '') ? $x['1'] : $x['0'];

			// Dynamically instantiate the class.

			$EE = new $class_name();

			// Does the method exist?
			if (method_exists($EE, $meth_name))
			{
				if (strtolower($class_name) == $meth_name)
				{
					$data = (isset($EE->return_data)) ? $EE->return_data : '';
				}
				else
				{
					$data = $EE->$meth_name();
				}
			}
		}

		return $data;
	}

	// --------------------------------------------------------------------

	/**
	 * Parse cron
	 *
	 * @access   public
	 * @return   boolean
	 */
	function parse_cron()
	{

		if ( ! file_exists($this->cache_file))
		{
			return TRUE;
		}

		list($this->last['minute'], $this->last['hour'], $this->last['day'], $this->last['month'], $this->last['weekday'], $this->last['year']) = explode(',', strftime("%M,%H,%d,%m,%w,%Y", $this->get_cache_file()));

		// ------------------------------
		//  Cron Parameters
		// ------------------------------

		foreach($this->params as $param)
		{
			if (ee()->TMPL->fetch_param($param) !== FALSE && ee()->TMPL->fetch_param($param) != '*')
			{
				$value = $this->expand_ranges(ee()->TMPL->fetch_param($param));
			}
			else
			{
				$value = array();

				switch($param)
				{
					case 'minute'	:
						$start = 0; $end = 59;
					break;

					case 'hour'		:
						$start = 0; $end = 23;
					break;

					case 'day'		:
						$start = 1; $end = 31;  // Maximum amount of days in month
					break;

					case 'month'	:
						$start = 1; $end = 12;
					break;

					case 'weekday'	:
						$start = 0; $end = 6;
					break;

					case 'year'	:
						$current_year = date("Y");
						$start = $current_year; $end = $current_year + 5;
					break;
				}

				for ($i=$start; $i <= $end; ++$i)
				{
					$value[] = $i;
				}
			}

			$this->crontab[$param] = $value;
		}

		// ---------------------------------
		//  Repeat Check, So Invalid
		// ---------------------------------

		// If even one is different, the last check was not this check

		$check_same = TRUE;

		foreach($this->params as $param)
		{
			if ($this->now[$param] != $this->last[$param])
			{
				$check_same = FALSE;
			}
		}

		if ($check_same === TRUE) return FALSE;


		// ------------------------------
		//  Two Valid Cases
		//
		//  1.  We are supposed to perform the cron now. To determine
		//  if this is the case we just see if the now matches up with all
		//  possible cron times in $this->crontab. We also have to make sure
		//  that we have not performed this cron check for 'now' already
		//
		//  2.  We missed a check and need to perform the cron now to make up
		//  for that little oversight.  To determine this, we find the last
		//  possible cron check time before now and see if there was a check
		//  at that time.
		// ------------------------------

		// ------------------------------
		//  Check the First Case
		// ------------------------------

		$check_now	= TRUE;

		foreach($this->params as $param)
		{
			// If even one is different, now is not the time to check

			if ( ! in_array($this->now[$param], $this->crontab[$param]))
			{
				$check_now = FALSE;
			}
		}

		if ($check_now === TRUE) return TRUE; // Success!



		// ------------------------------
		//  Check the Second Case
		//
		//  This is a great deal less enjoyable, since we have to find the
		//  precise last possible check before this point in time.  Once
		//  we figure that out though, it is all so easy.
		// ------------------------------

		$this->last_possible = $this->find_last_possible_alt(); // returns time array

		if ($this->last_possible === FALSE)
		{
			return FALSE;
		}

		$check_done	= TRUE;

		foreach($this->params as $param)
		{
			// If even one is different, the last check was not performed
			// Very sad, but we can do it now and save ourselves from certain doom

			if ($this->last[$param] != $this->last_possible[$param])
			{
				$check_done = FALSE;
			}
		}

		if ($check_done === FALSE)
		{
			return TRUE; // Success!
		}

		// ---------------------------------
		//  Not time to check, take a nap
		// ---------------------------------

		return FALSE;
	}

	// --------------------------------------------------------------------

	// ------------------------------------
	//  Find Last Possible Check Before Now
	//
	//  Finds the last possible check for this cron before the current
	//  time.  Assumes that there is a possible check before this one, but
	//  I wonder if that is a bad assumption.  I do know that the weekday
	//  parameter is going to be the most annoying though.  Feel it in my bones
	//  I do!
	// ------------------------------------

	/**
	 * Find last possible check before the current time
	 *
	 * @access   public
	 * @return   string
	 */
	function find_last_possible()
	{
		$i = 0;
		$x = array();

		while(TRUE)
		{
			$this->check -= 60;  // Knock off sixty seconds

			//  I have no idea how efficient this is, needs checking into.
			//  I hope it is not too much slower than doing it manually
			//  with subtraction because that is messy and this is clean

			list($x['minute'], $x['hour'], $x['day'], $x['month'], $x['weekday'], $x['year']) = explode(',', strftime("%M,%H,%d,%m,%w,%Y", $this->check));

			$valid	= TRUE;

			foreach($this->params as $param)
			{
				// If even one is different, now is not the time to check

				if ( ! in_array($x[$param], $this->crontab[$param]))
				{
					$valid = FALSE;
				}
			}

			if ($valid === TRUE)
			{
				return $x;
			}

			$i++;

			if ($i > 45000)
			{
				break; // Safety, approximately a one month check
			}
		}

		return FALSE;
	}

	// --------------------------------------------------------------------

	// -----------------------------------------
	//  Faster version of the function, which seems to be
	//  just a little bit faster so we use it unless there is
	//  some problem.
	// ----------------------------------------


	/**
	 * Alternative last find function
	 *
	 * @access   public
	 * @return   string
	 */
	function find_last_possible_alt()
	{
		$i = 0;
		$x = $this->now;

		while(TRUE)
		{
			// ------------------------------
			//  Reduce, Reuse, Recycle
			// ------------------------------

			$x['minute']--;

			if ($x['minute'] < 0)
			{
				$x['minute'] = 59;
				$x['hour']--;

				if ($x['hour'] < 0)
				{
					$x['hour'] = 23;
					$x['day']--;

					if($x['day'] < 1)
					{
						$x['month']--;

						if ($x['month'] < 1)
						{
							$x['month'] = 12;
							$x['year']--;
						}

						$x['day'] = $this->days_in_month($x['month'], $x['year']);
					}

					$x['weekday']--;

					if ($x['weekday'] < 0)
					{
						$x['weekday'] = 6;
					}
				}
			}

			// ------------------------------
			//  Check to see if it is a valid cron check
			// ------------------------------

			$valid	= TRUE;

			foreach($this->params as $param)
			{
				// If even one is different, now is not the time to check

				if ( ! in_array($x[$param], $this->crontab[$param]))
				{
					$valid = FALSE;
				}
			}

			if ($valid === TRUE)
			{
				$this->check = mktime($x['hour'], $x['minute'], 1, $x['month'], $x['day'], $x['year']);
				return $x;
			}

			// ------------------------------------
			//  Go Back No Further Than Last Check
			// ------------------------------------

			$end_check = TRUE;

			foreach($this->params as $param)
			{
				if ($x[$param] != $this->last[$param])
				{
					$end_check = FALSE;
				}
			}

			if ($end_check === TRUE)
			{
				return FALSE;
			}

			// ------------------------------------
			//  Safety Check
			// ------------------------------------

			$i++;

			if ($i > 45000)
			{
				break; // Safety, approximately a one month check
			}
		}

		return FALSE;
	}

	// --------------------------------------------------------------------

 	// ------------------------------------
	//  Expands Ranges in a Value
	//
	//  Assumes that value is not *, and creates an array of
	//  valid numbers that the string represents.  Returns an array.
	// ------------------------------------

	/**
	 * Expand ranges
	 *
	 * Creates an array of valid numbers that the string represents
	 *
	 * @access   public
	 * @param	string
	 * @return   array
	 */
	function expand_ranges($str)
	{
		if (strstr($str,  ","))
		{
			$tmp1 = explode(",", $str);

			$count = count($tmp1);

			//Loop through each comma-separated value

			for ($i = 0; $i < $count; $i++)
			{
				if (strstr($tmp1[$i],  "-"))
				{
					// Expand Any Ranges
					$tmp2 = explode("-", $tmp1[$i]);

					for ($j = $tmp2[0]; $j <= $tmp2[1]; $j++)
					{
						$ret[] = $j;
					}

				}
				else
				{
					$ret[] = $tmp1[$i];
				}
			}

		}
		elseif(strstr($str,  "-"))
		{
			// Range Only, No Commas
			$range = explode("-", $str);

			for ($i = $range[0]; $i <= $range[1]; $i++)
			{
				$ret[] = $i;
			}

		}
		else
		{
			// Single Value, Only
			$ret[] = $str;
		}

		return $ret;
	}

	// --------------------------------------------------------------------

	// ---------------------------------------------
	//  Returns Weekday Number for Date
	// ---------------------------------------------

	/**
	 * Day of week
	 *
	 * Returns weekday number for date
	 *
	 * @access   public
	 * @param	string
	 * @param	string
	 * @param	string
	 * @return   number
	 */
	function day_of_week($month, $day, $year)
	{
		if ( ! function_exists('gregoriantojd'))
		{
			return date('w', mktime(0, 1, 1, $month, $day, $year));
		}
		else
		{
			return jddayofweek(gregoriantojd($month, $day, $year));
		}
	}

	// --------------------------------------------------------------------

	/**
	 * Days in month
	 *
	 * Returns Days in a Specific Month/Year
	 *
	 * @access   public
	 * @param	type
	 * @return   type
	 */
	function days_in_month($month, $year)
	{
		if(checkdate($month, 31, $year)) return 31;
		if(checkdate($month, 30, $year)) return 30;
		if(checkdate($month, 29, $year)) return 29;
		if(checkdate($month, 28, $year)) return 28;
		return 0; // error
	}

	// --------------------------------------------------------------------

	/**
	 * Debug
	 *
	 *
	 * @access   public
	 * @param	string
	 * @return   void
	 */

	function debug($str)
	{
		// echo $str;
	}

	// --------------------------------------------------------------------

	/**
	 * Write cache
	 *
	 * @access   public
	 * @return   void
	 */
	function write_cache()
	{
		if ( ! @is_dir(APPPATH.'cache/'.$this->cache_name))
		{
			if ( ! @mkdir(APPPATH.'cache/'.$this->cache_name, 0777))
			{
				return FALSE;
			}

			@chmod(APPPATH.'cache/'.$this->cache_name, 0777);
		}

		if ($fp = @fopen($this->cache_file, 'wb'))
		{
			flock($fp, LOCK_EX);
			fwrite($fp, $this->check);
			flock($fp, LOCK_UN);
			fclose($fp);
		}

		@chmod($this->cache_file, 0777);
	}

	// --------------------------------------------------------------------

	/**
	 * Get cache file
	 *
	 * @access   public
	 * @return   string
	 */
	function get_cache_file()
	{
		$cache = '';

		if ($fp = @fopen($this->cache_file, 'rb'))
		{
			flock($fp, LOCK_SH);

			$cache = @fread($fp, filesize($this->cache_file));

			flock($fp, LOCK_UN);

			fclose($fp);
		}

		return trim($cache);

	}

	// --------------------------------------------------------------------

}
// END CLASS

// EOF
