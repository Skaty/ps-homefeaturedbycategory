<?php
/*
* Original (C) 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
*
*  @author Syed Abdullah <GitHub: Skaty>. Derived from code by PrestaShop SA <contact@prestashop.com>
*  @copyright  Syed Abdullah. Original code (c) 2007-2014 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*
*/

if (!defined('_PS_VERSION_'))
	exit;

class HomeFeaturedByCategory extends Module
{
	protected static $cache_products; // now it's an array!

	public function __construct()
	{
		$this->name = 'homefeaturedbycategory';
		$this->tab = 'front_office_features';
		$this->version = '1.0.0';
		$this->author = 'Syed Abdullah';
		$this->need_instance = 0;

		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l('Featured products grouped by categories on the homepage');
		$this->description = $this->l('Displays featured products grouped by selected categories on the homepage.');
	}

	public function install()
	{
		$this->_clearCache('*');
		Configuration::updateValue('HOME_FEATUREDBCATNBR', 8);
		Configuration::updateValue('HOME_FEATUREDBCATCATS', serialize(array((int)Context::getContext()->shop->getCategory())));
		Configuration::updateValue('HOME_FEATUREDBCATRANDOMIZE', false);

		if (!parent::install()
			|| !$this->registerHook('header')
			|| !$this->registerHook('addproduct')
			|| !$this->registerHook('updateproduct')
			|| !$this->registerHook('deleteproduct')
			|| !$this->registerHook('categoryUpdate')
			/*|| !$this->registerHook('displayHomeTab')
			|| !$this->registerHook('displayHomeTabContent')*/
		)
			return false;

		return true;
	}

	public function uninstall()
	{
		$this->_clearCache('*');

		return parent::uninstall();
	}

	public function getContent()
	{
		$output = '';
		$errors = array();
		if (Tools::isSubmit('submitHomeFeatured'))
		{
			$nbr = Tools::getValue('HOME_FEATUREDBCAT_NBR');
			if (!Validate::isInt($nbr) || $nbr <= 0)
			$errors[] = $this->l('The number of products is invalid. Please enter a positive number.');

			$cat = Tools::getValue('HOME_FEATUREDBCAT_CATS');
			if (!Validate::isArrayWithIds($cat))
				$errors[] = $this->l('Please select at least one category.');

			$rand = Tools::getValue('HOME_FEATUREDBCAT_RANDOMIZE');
			if (!Validate::isBool($rand))
				$errors[] = $this->l('Invalid value for the "randomize" flag.');
			if (isset($errors) && count($errors))
				$output = $this->displayError(implode('<br />', $errors));
			else
			{
				Configuration::updateValue('HOME_FEATUREDBCAT_NBR', (int)$nbr);
				Configuration::updateValue('HOME_FEATUREDBCAT_CATS', serialize($cat));
				Configuration::updateValue('HOME_FEATUREDBCAT_RANDOMIZE', (bool)$rand);
				Tools::clearCache(Context::getContext()->smarty, $this->getTemplatePath('homefeaturedbycategory.tpl'));
				$output = $this->displayConfirmation($this->l('Your settings have been updated.'));
			}
		}

		return $output.$this->renderForm();
	}

	public function hookDisplayHeader($params)
	{
		$this->hookHeader($params);
	}

	public function hookHeader($params)
	{
		if (isset($this->context->controller->php_self) && $this->context->controller->php_self == 'index')
			$this->context->controller->addCSS(_THEME_CSS_DIR_.'product_list.css');
		$this->context->controller->addCSS(($this->_path).'homefeatured.css', 'all');
	}

	public function _cacheProducts()
	{
		if (!isset(HomeFeaturedByCategory::$cache_products))
		{
			$cats = (array)unserialize(Configuration::get('HOME_FEATUREDBCAT_CATS'));
			$nb = (int)Configuration::get('HOME_FEATUREDBCAT_NBR');
			foreach ($cats as $cat)
			{
				$category = new Category($cat, (int)Context::getContext()->language->id);
				if (Configuration::get('HOME_FEATUREDBCAT_RANDOMIZE'))
					HomeFeaturedByCategory::$cache_products[$category->name] = $category->getProducts((int)Context::getContext()->language->id, 1, ($nb ? $nb : 8), null, null, false, true, true, ($nb ? $nb : 8));
				else
					HomeFeaturedByCategory::$cache_products[$category->name] = $category->getProducts((int)Context::getContext()->language->id, 1, ($nb ? $nb : 8), 'position');
			}
		}

		if (HomeFeaturedByCategory::$cache_products === false || empty(HomeFeaturedByCategory::$cache_products))
			return false;
	}
	/* WIP LO!
	public function hookDisplayHomeTab($params)
	{
		if (!$this->isCached('tab.tpl', $this->getCacheId('homefeatured-tab')))
			$this->_cacheProducts();

		return $this->display(__FILE__, 'tab.tpl', $this->getCacheId('homefeatured-tab'));
	}
	*/
	public function hookDisplayHome($params)
	{
		if (!$this->isCached('homefeaturedbycategory.tpl', $this->getCacheId()))
		{
			$this->_cacheProducts();
			$this->smarty->assign(
				array(
					'catproducts' => HomeFeaturedByCategory::$cache_products,
					'add_prod_display' => Configuration::get('PS_ATTRIBUTE_CATEGORY_DISPLAY'),
					'homeSize' => Image::getSize(ImageType::getFormatedName('home')),
				)
			);
		}

		return $this->display(__FILE__, 'homefeaturedbycategory.tpl', $this->getCacheId());
	}
	/*
	public function hookDisplayHomeTabContent($params)
	{
		return $this->hookDisplayHome($params);
	}
	*/
	public function hookAddProduct($params)
	{
		$this->_clearCache('*');
	}

	public function hookUpdateProduct($params)
	{
		$this->_clearCache('*');
	}

	public function hookDeleteProduct($params)
	{
		$this->_clearCache('*');
	}

	public function hookCategoryUpdate($params)
	{
		$this->_clearCache('*');
	}

	public function _clearCache($template, $cache_id = NULL, $compile_id = NULL)
	{
		parent::_clearCache('homefeaturedbycategory.tpl');
		//parent::_clearCache('tab.tpl', 'homefeatured-tab');
	}

	public function renderForm()
	{
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Settings'),
					'icon' => 'icon-cogs'
				),
				'description' => $this->l('All products from selected categories will be displayed, sorted by category.'),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->l('Number of products per category to be displayed'),
						'name' => 'HOME_FEATUREDBCATNBR',
						'class' => 'fixed-width-xs',
						'desc' => $this->l('Set the number of products per category that you would like to display (default: 8).'),
					),
					array(
						'type' => 'categories',
						'label' => $this->l('Category from which to pick products to be displayed'),
						'name' => 'HOME_FEATUREDBCATCATS',
						'class' => 'fixed-width-xs',
						'desc' => $this->l('Select the categories of the products that you would like to display on the homepage.'),
						'tree' => array(
							'id' => 'HOME_FEATUREDBCATCATS',
							'use_checkbox' => true,
							'selected_categories' => (array)unserialize(Configuration::get('HOME_FEATUREDBCATCATS'))
							)
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Randomly display featured products'),
						'name' => 'HOME_FEATUREDBCATRANDOMIZE',
						'class' => 'fixed-width-xs',
						'desc' => $this->l('Enable if you wish the products to be displayed randomly.'),
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						),
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			),
		);

		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();
		$helper->id = (int)Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitHomeFeatured';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
			'fields_value' => $this->getConfigFieldsValues(),
			'languages' => $this->context->controller->getLanguages(),
			'id_language' => $this->context->language->id
		);

		return $helper->generateForm(array($fields_form));
	}

	public function getConfigFieldsValues()
	{
		return array(
			'HOME_FEATUREDBCATNBR' => Tools::getValue('HOME_FEATUREDBCATNBR', (int)Configuration::get('HOME_FEATUREDBCATNBR')),
			'HOME_FEATUREDBCATCATS' => Tools::getValue('HOME_FEATUREDBCATCATS', (array)unserialize(Configuration::get('HOME_FEATUREDBCATCATS'))),
			'HOME_FEATUREDBCATRANDOMIZE' => Tools::getValue('HOME_FEATUREDBCATRANDOMIZE', (bool)Configuration::get('HOME_FEATUREDBCATRANDOMIZE')),
		);
	}
}
