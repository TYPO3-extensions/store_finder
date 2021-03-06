<?php
namespace Evoweb\StoreFinder\ViewHelpers;
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2013 Sebastian Fischer <typo3@evoweb.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Class MinifyViewHelper
 *
 * @package Evoweb\StoreFinder\ViewHelpers
 */
class MinifyViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper {
	/**
	 * Renders the content minified
	 *
	 * @param string $content
	 * @return string
	 */
	public function render($content = '') {
		$content = $content ? $content : $this->renderChildren();

		/* remove comments */
		$content = str_replace('://', "\xff", $content);
		$content = preg_replace('@((?:\/\*(?:[^*]|(?:\*+[^*\/]))*\*+\/)|(?:\/\/.*))@', '', $content);
		$content = str_replace("\xff", '://', $content);

		/* remove tabs, spaces, newlines, etc. */
		$content = str_replace(
			array(CRLF, CR, LF, TAB, '     ', '    ', '  ', ': '),
			array('', '', '', '', '', '', '', ':'),
			$content
		);
		/* remove other spaces before/after ) */
		$content = preg_replace(array('(( )+\))', '(\)( )+)'), ')', $content);

		return $content;
	}
}