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

use RegularLabs\Library\FieldGroup;

defined('_JEXEC') or die;

if ( ! is_file(JPATH_LIBRARIES . '/regularlabs/autoload.php'))
{
    return;
}

require_once JPATH_LIBRARIES . '/regularlabs/autoload.php';

class JFormFieldRL_FlexiContent extends FieldGroup
{
    public $default_group = 'Tags';
    public $type          = 'FlexiContent';

    public function getTags()
    {
        $query = $this->db->getQuery(true)
            ->select('t.name as id, t.name')
            ->from('#__flexicontent_tags AS t')
            ->where('t.published = 1')
            ->where('t.name != ' . $this->db->quote(''))
            ->group('t.name')
            ->order('t.name');
        $this->db->setQuery($query);
        $list = $this->db->loadObjectList();

        return $this->getOptionsByList($list);
    }

    public function getTypes()
    {
        $query = $this->db->getQuery(true)
            ->select('t.id, t.name')
            ->from('#__flexicontent_types AS t')
            ->where('t.published = 1')
            ->order('t.name, t.id');
        $this->db->setQuery($query);
        $list = $this->db->loadObjectList();

        return $this->getOptionsByList($list);
    }

    protected function getInput()
    {
        $error = $this->missingFilesOrTables(['tags', 'types']);

        return $error ?: $this->getSelectList();
    }
}
