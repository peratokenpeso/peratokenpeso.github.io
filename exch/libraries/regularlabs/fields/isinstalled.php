<?php
/**
 * @package         Regular Labs Library
 * @version         23.9.3039
 * 
 * @author          Peter van Westen <info@regularlabs.com>
 * @link            https://regularlabs.com
 * @copyright       Copyright © 2023 Regular Labs All Rights Reserved
 * @license         GNU General Public License version 2 or later
 */

defined('_JEXEC') or die;

use RegularLabs\Library\Extension as RL_Extension;
use RegularLabs\Library\Field;

jimport('joomla.form.formfield');

if ( ! is_file(JPATH_LIBRARIES . '/regularlabs/autoload.php'))
{
    return;
}

require_once JPATH_LIBRARIES . '/regularlabs/autoload.php';

class JFormFieldRL_IsInstalled extends Field
{
    public $type = 'IsInstalled';

    protected function getInput()
    {
        $is_installed = RL_Extension::isInstalled($this->get('extension'), $this->get('extension_type'), $this->get('folder'));

        return '<input type="hidden" name="' . $this->name . '" id="' . $this->id . '" value="' . (int) $is_installed . '">';
    }

    protected function getLabel()
    {
        return '';
    }
}
