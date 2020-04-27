<?php
/**
 * Main MealParser class, used to parse MealMaster recipes
 *
 * @package Teamwork
 * @copyright 2009 Timo Puschkasch
 *
 * This file is part of MealParser.
 *
 * MealParser is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * MealParser is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with MealParser.  If not, see <http://www.gnu.org/licenses/>.
 */

class MealParser
{
    const TITLE_STRING = 'Title: ';

    /**
     * The recipes in this parse
     * @var array
     */
    protected $recipes = array();

	protected $lastAmount = 0;
	protected $lastUnit = '';
	
    /**
     * The current recipe
     * @var array
     */
    protected $current = array();

    /**
     * Whether we are inside a recipe or not
     * @var boolean
     */
    protected $titled = false;

	protected $imode = false;
	
    /**
     * Parse a single line
     * @param string $line
     */
    public function parseLine($line)
    {
        $linet = trim($line);
        $rxpTitle = '/^Title: .+/i';
        $rxpTags  = '/^Categories: .+/i';
        $rxpYield = '/^Servings: [1-9][0-9]*/';
        $rxpYield2= '/^Yield: [1-9][0-9]*/';
        $rxpEnd = '/^(M{5,5}|-{5,5})(-{5,5})?\s*$/';
		$rxpComment = '/^M{5,5}[-]+.+[-]+/';

		if (preg_match($rxpComment, $linet)) {
			return;
		}
        if (preg_match($rxpTitle, $linet)) {
            $this->current['title'] = substr($linet, 7);
            $this->titled = true;
        } elseif (preg_match($rxpTags, $linet)) {
            $this->current['tags'] = explode(',', substr($linet, 12));
            foreach ($this->current['tags'] as $id => $tag) {
                $this->current['tags'][$id] = trim($tag);
            }
        } elseif (preg_match($rxpYield, $linet)) {
            $this->current['servings'] = filter_var(substr($linet, 10), FILTER_SANITIZE_NUMBER_INT);
        } elseif (preg_match($rxpYield2, $linet)) {
            $this->current['servings'] = filter_var(substr($linet, 7), FILTER_SANITIZE_NUMBER_INT);
        } elseif (preg_match($rxpEnd, $linet)) {
            $this->recipes[] = $this->current;
            $this->current = array();
            $this->titled = false;
			$this->imode = false;
        } else {
			if ($this->imode) {
				if ( !(isset($linet[0]) && $linet[0] == ':')) {
					$this->current['instructions'] .= $linet . "\n";
				}
				return;
			}
            if (!$this->titled) {
                return;
            }

            $amount = substr( $line, 0, 7); // col 0-6
            $measurement = substr( $line, 8, 2); // col 8-9
            $name = substr( $line, 11); // col 11+

            if (((int)$amount == 0) && (trim($amount . $measurement) != '')) {
                if (!isset($this->current['instructions'])) {
                    $this->current['instructions'] = '';
                }
                $this->current['instructions'] .= trim($line) . "\n";
				$this->imode = true;
            } else {
                $amount = trim($amount);
                $measurement = trim($measurement);
                $name = trim($name);
                if ($name != '') {
                    if ($name[0] == '-') {
                        $last = array_pop($this->current['ingredients']);
                        $name = $last['name'] . substr($name, 1); 
						$amount = $last['amount'];
						$measurement = $last['unit'];
                    }
					if (empty($amount)) {
						$amount = $this->lastAmount;
					}
					if (empty($measurement)) {
						$measurement = $this->lastUnit;
					}
					$this->lastAmount = $amount;
					$this->lastUnit   = $measurement;
                    $this->current['ingredients'][] = array('name' => $name, 'amount' => $amount, 'unit' => $measurement);
                }
            }
        }
    }

    /**
     * Parse an array of lines
     * @param array $array The lines to parse
     * @return array
     */
    public function parseArray(array $array)
    {
        //iterate through the file contents
        foreach ($array as $lineNumber => $line) {
            //trim whitespaces
            $this->parseLine($line);
        }
        return $this->recipes;
    }

    /**
     * Check if a string starts with another string
     * @param $haystack
     * @param $needle
     * @return boolean
     */
    private function _startsWith($haystack, $needle)
    {
        return strpos($Haystack, $Needle) === 0;
    }

    /**
     * Parse a mealmaster file
     * @param string $fileName
     * @return array
     */
    public function parseFile($fileName)
    {
        //make sure the filename is a string
        if (!is_string($fileName)) {
            throw new Exception('File name must be a string');
        }
        //try to read the file contents
        $data = file($fileName, FILE_IGNORE_NEW_LINES);
        if ($data) {
            //parse the file contents
            return $this->parseArray($data);
        } else {
            //the file contents are unreadable
            return array();
        }
    }
    
    /**
     * Get all parsed recipes
     * @return array
     */
    public function getRecipes()
    {
        return $this->recipes;
    }
}