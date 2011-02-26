<?php

/**
 * In this file we store all generic functions that we will be using in the locale module
 *
 * @package		backend
 * @subpackage	locale
 *
 * @author		Davy Hellemans <davy@netlash.com>
 * @author		Tijs Verkoyen <tijs@sumocoders.be>
 * @author		Dieter Vanden Eynde <dieter@dieterve.be>
 * @since		2.0
 */
class BackendLocaleModel
{
	/**
	 * Build the language files
	 *
	 * @return	void
	 * @param	string $language		The language to build the locale-file for.
	 * @param	string $application		The application to build the locale-file for.
	 */
	public static function buildCache($language, $application)
	{
		// get db
		$db = BackendModel::getDB();

		// get types
		$types = $db->getEnumValues('locale', 'type');

		// get locale for backend
		$locale = (array) $db->getRecords('SELECT type, module, name, value
											FROM locale
											WHERE language = ? AND application = ?
											ORDER BY type ASC, name ASC, module ASC',
											array((string) $language, (string) $application));

		// start generating PHP
		$value = '<?php' ."\n";
		$value .= '/**' ."\n";
		$value .= ' *' ."\n";
		$value .= ' * This file is generated by Fork CMS, it contains' ."\n";
		$value .= ' * more information about the locale. Do NOT edit.' ."\n";
		$value .= ' * ' ."\n";
		$value .= ' * @author		Fork CMS' ."\n";
		$value .= ' * @generated	'. date('Y-m-d H:i:s') ."\n";
		$value .= ' */' ."\n";
		$value .= "\n";

		// loop types
		foreach($types as $type)
		{
			// default module
			$modules = array('core');

			// continue output
			$value .= "\n";
			$value .= '// init var'. "\n";
			$value .= '$'. $type .' = array();' ."\n";
			$value .= '$'. $type .'[\'core\'] = array();' ."\n";

			// loop locale
			foreach($locale as $i => $item)
			{
				// types match
				if($item['type'] == $type)
				{
					// new module
					if(!in_array($item['module'], $modules))
					{
						$value .= '$'. $type .'[\''. $item['module'] .'\'] = array();'. "\n";
						$modules[] = $item['module'];
					}

					// parse
					if($application == 'backend') $value .= '$'. $type .'[\''. $item['module'] .'\'][\''. $item['name'] .'\'] = \''. str_replace('\"', '"', addslashes($item['value'])) .'\';'. "\n";
					else $value .= '$'. $type .'[\''. $item['name'] .'\'] = \''. str_replace('\"', '"', addslashes($item['value'])) .'\';'. "\n";

					// unset
					unset($locale[$i]);
				}
			}
		}

		// close php
		$value .= "\n";
		$value .= '?>';

		// store
		SpoonFile::setContent(constant(mb_strtoupper($application) .'_CACHE_PATH') .'/locale/'. $language .'.php', $value);
	}


	/**
	 * Delete (multiple) items from locale
	 *
	 * @return	void
	 * @param	array $ids	The id(s) to delete.
	 */
	public static function delete(array $ids)
	{
		// delete records
		BackendModel::getDB(true)->delete('locale', 'id IN ('. implode(',', $ids) .')');

		// rebuild cache
		self::buildCache('nl', 'backend');
		self::buildCache('nl', 'frontend');
	}


	/**
	 * Does an id exist.
	 *
	 * @return	bool
	 * @param	int $id		The id to check for existence.
	 */
	public static function exists($id)
	{
		return (bool) BackendModel::getDB()->getVar('SELECT COUNT(id)
														FROM locale
														WHERE id = ?',
														array((int) $id));
	}


	/**
	 * Does a locale exists by its name.
	 *
	 * @return	bool
	 * @param	string $name			The name of the locale.
	 * @param	string $type			The type of the locale.
	 * @param	string $module			The module wherin will be searched.
	 * @param	string $language		The language to use.
	 * @param	string $application		The application wherin will be searched.
	 * @param	int[optional] $id		The id to exclude in the check.
	 */
	public static function existsByName($name, $type, $module, $language, $application, $id = null)
	{
		// redefine
		$name = (string) $name;
		$type = (string) $type;
		$module = (string) $module;
		$language = (string) $language;
		$application = (string) $application;
		$id = ($id !== null) ? (int) $id : null;

		// get db
		$db = BackendModel::getDB();

		// return
		if($id !== null) return (bool) $db->getVar('SELECT COUNT(id)
													FROM locale
													WHERE name = ? AND type = ? AND module = ? AND language = ? AND application = ? AND id != ?',
													array($name, $type, $module, $language, $application, $id));

		return (bool) BackendModel::getDB()->getVar('SELECT COUNT(id)
														FROM locale
														WHERE name = ? AND type = ? AND module = ? AND language = ? AND application = ?',
														array($name, $type, $module, $language, $application));
	}


	/**
	 * Get a single item from locale.
	 *
	 * @return	array
	 * @param	int $id		The id of the item to get.
	 */
	public static function get($id)
	{
		return (array) BackendModel::getDB()->getRecord('SELECT * FROM locale WHERE id = ?', array((int) $id));
	}


	/**
	 * Get full type name.
	 *
	 * @return	string
	 * @param	string $type
	 */
	public static function getTypeName($type)
	{
		// get full type name
		switch($type)
		{
			case 'act':
				$type = 'action';
			break;
			case 'err':
				$type = 'error';
			break;
			case 'lbl':
				$type = 'label';
			break;
			case 'msg':
				$type = 'message';
			break;
		}

		// cough up full name
		return $type;
	}


	/**
	 * Get all locale types.
	 *
	 * @return	array
	 */
	public static function getTypesForDropDown()
	{
		// fetch types
		$types = BackendModel::getDB()->getEnumValues('locale', 'type');

		// init
		$labels = $types;

		// loop and build labels
		foreach($labels as &$row) $row = ucfirst(BL::msg(mb_strtoupper($row), 'core'));

		// build array
		return array_combine($types, $labels);
	}


	/**
	 * Import a locale XML file.
	 *
	 * @return	void
	 * @param	SimpleXMLElement $xml				The locale XML.
	 * @param	bool[optional] $overwriteConflicts	Should we overwrite when there is a conflict?
	 */
	public static function importXML(SimpleXMLElement $xml, $overwriteConflicts = false)
	{
		// recast
		$overwriteConflicts = (bool) $overwriteConflicts;

		// possible values
		$possibleApplications = array('frontend', 'backend');
		$possibleModules = BackendModel::getModules(false);
		$possibleLanguages = BL::getActiveLanguages();
		$possibleTypes = array();

		// types
		$typesShort = BackendModel::getDB()->getEnumValues('locale', 'type');
		foreach($typesShort as $type) $possibleTypes[$type] = self::getTypeName($type);

		// current locale items (used to check for conflicts)
		$currentLocale = BackendModel::getDB()->getColumn('SELECT CONCAT(application, module, type, language, name) FROM locale');

		// applications
		foreach($xml as $application => $modules)
		{
			// application does not exist
			if(!in_array($application, $possibleApplications)) continue;

			// modules
			foreach($modules as $module => $items)
			{
				// module does not exist
				if(!in_array($module, $possibleModules)) continue;

				// items
				foreach($items as $item)
				{
					// attributes
					$attributes = $item->attributes();
					$type = SpoonFilter::getValue($attributes['type'], $possibleTypes, '');
					$name = SpoonFilter::getValue($attributes['name'], null, '');

					// missing attributes
					if($type == '' || $name == '') continue;

					// real type (shortened)
					$type = array_search($type, $possibleTypes);

					// translations
					foreach($item->translation as $translation)
					{
						// attributes
						$attributes = $translation->attributes();
						$language = SpoonFilter::getValue($attributes['language'], $possibleLanguages, '');

						// language does not exist
						if($language == '') continue;

						// the actual translation
						$translation = (string) $translation;

						// locale item
						$locale['user_id'] = BackendAuthentication::getUser()->getUserId();
						$locale['language'] = $language;
						$locale['application'] = $application;
						$locale['module'] = $module;
						$locale['type'] = $type;
						$locale['name'] = $name;
						$locale['value'] = $translation;
						$locale['edited_on'] = BackendModel::getUTCDate();

						// found a conflict, overwrite it with the imported translation
						if($overwriteConflicts && in_array($application . $module . $type . $language . $name, $currentLocale))
						{
							// overwrite
							BackendModel::getDB(true)->update('locale',
																$locale,
																'application = ? AND module = ? AND type = ? AND language = ? AND name = ?',
																array($application, $module, $type, $language, $name));
						}

						// insert translation that doesnt exists yet
						elseif(!in_array($application . $module . $type . $language . $name, $currentLocale))
						{
							// insert
							BackendModel::getDB(true)->insert('locale', $locale);
						}
					}
				}
			}
		}

		// rebuild cache
		foreach($possibleApplications as $application)
		{
			foreach($possibleLanguages as $language) self::buildCache($language, $application);
		}
	}


	/**
	 * Insert a new locale item.
	 *
	 * @return	int
	 * @param	array $item		The data to insert.
	 */
	public static function insert(array $item)
	{
		// insert item
		$item['id'] = (int) BackendModel::getDB(true)->insert('locale', $item);

		// rebuild the cache
		self::buildCache($item['language'], $item['application']);

		// return the new id
		return $item['id'];
	}


	/**
	 * Update a locale item.
	 *
	 * @return	void
	 * @param	array $item		The new data.
	 */
	public static function update(array $item)
	{
		// update category
		$updated = BackendModel::getDB(true)->update('locale', $item, 'id = ?', array($item['id']));

		// rebuild the cache
		self::buildCache($item['language'], $item['application']);

		// return
		return $updated;
	}
}

?>