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

namespace RegularLabs\Library\Condition;

defined('_JEXEC') or die;

/**
 * Class MijoshopCategory
 *
 * @package RegularLabs\Library\Condition
 */
class MijoshopCategory extends Mijoshop
{
    public function pass()
    {
        if ($this->request->option != 'com_mijoshop')
        {
            return $this->_(false);
        }

        $pass = (
            ($this->params->inc_categories
                && ($this->request->view == 'category')
            )
            || ($this->params->inc_items && $this->request->view == 'product')
        );

        if ( ! $pass)
        {
            return $this->_(false);
        }

        $cats = $this->getCats();

        $pass = $this->passSimple($cats, false, 'include');

        if ($pass && $this->params->inc_children == 2)
        {
            return $this->_(false);
        }

        if ( ! $pass && $this->params->inc_children)
        {
            foreach ($cats as $cat)
            {
                $cats = array_merge($cats, $this->getCatParentIds($cat));
            }
        }

        return $this->passSimple($cats);
    }

    private function getCatParentIds($id = 0)
    {
        return $this->getParentIds($id, 'mijoshop_category', 'parent_id', 'category_id');
    }

    private function getCats()
    {
        if ($this->request->category_id)
        {
            return $this->makeArray($this->request->category_id);
        }

        if ( ! $this->request->item_id)
        {
            return [];
        }

        $query = $this->db->getQuery(true)
            ->select('c.category_id')
            ->from('#__mijoshop_product_to_category AS c')
            ->where('c.product_id = ' . (int) $this->request->id);
        $this->db->setQuery($query);
        $cats = $this->db->loadColumn();

        return $this->makeArray($cats);
    }
}
