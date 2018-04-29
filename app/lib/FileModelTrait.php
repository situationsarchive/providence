<?php
/** ---------------------------------------------------------------------
 * app/lib/FileModelTrait.php :
 * ----------------------------------------------------------------------
 * CollectiveAccess
 * Open-source collections management software
 * ----------------------------------------------------------------------
 *
 * Software by Whirl-i-Gig (http://www.whirl-i-gig.com)
 * Copyright 2018 Whirl-i-Gig
 *
 * For more information visit http://www.CollectiveAccess.org
 *
 * This program is free software; you may redistribute it and/or modify it under
 * the terms of the provided license as published by Whirl-i-Gig
 *
 * CollectiveAccess is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTIES whatsoever, including any implied warranty of 
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  
 *
 * This source code is free and modifiable under the terms of 
 * GNU General Public License. (http://www.gnu.org/copyleft/gpl.html). See
 * the "license.txt" file for details, or visit the CollectiveAccess web site at
 * http://www.CollectiveAccess.org
 *
 * @package CollectiveAccess
 * @subpackage BaseModel
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License version 3
 *
 * ----------------------------------------------------------------------
 */
 
trait FileModelTrait {
	# --------------------------------------------------------------------------------
	# --- Uploaded file handling
	# --------------------------------------------------------------------------------
	/**
	 * Returns url of file
	 * 
	 * @access public
	 * @param $field field name
	 * @return string file url
	 */ 
	public function getFileUrl($ps_field) {
		$va_file_info = $this->get($ps_field, array('returnWithStructure' => true));
		if (!is_array($va_file_info) || !is_array($va_file_info = array_shift($va_file_info))) {
			return null;
		}

		$va_volume_info = self::$_FILE_VOLUMES->getVolumeInformation($va_file_info["VOLUME"]);
		if (!is_array($va_volume_info)) {
			return null;
		}

		$vs_protocol = $va_volume_info["protocol"];
		$vs_host = $va_volume_info["hostname"];
		$vs_path = join("/",array($va_volume_info["urlPath"], $va_file_info["HASH"], $va_file_info["MAGIC"]."_".$va_file_info["FILENAME"]));
		return $va_file_info["FILENAME"] ? "{$vs_protocol}://{$vs_host}.{$vs_path}" : "";
	}
	# --------------------------------------------------------------------------------
	/**
	 * Returns path of file
	 * 
	 * @access public
	 * @param string $field field name
	 * @return string path in local filesystem
	 */
	public function getFilePath($ps_field) {
		$va_file_info = $this->get($ps_field, array('returnWithStructure' => true));
		if (!is_array($va_file_info) || !is_array($va_file_info = array_shift($va_file_info))) {
			return null;
		}
		$va_volume_info = self::$_FILE_VOLUMES->getVolumeInformation($va_file_info["VOLUME"]);
		
		if (!is_array($va_volume_info)) {
			return null;
		}
		return join("/",array($va_volume_info["absolutePath"], $va_file_info["HASH"], $va_file_info["MAGIC"]."_".$va_file_info["FILENAME"]));
	}
	# --------------------------------------------------------------------------------
	/**
	 * Wrapper around BaseModel::get(), used to fetch information about files
	 * 
	 * @access public
	 * @param string $field field name
	 * @return array file information
	 */
	public function &getFileInfo($ps_field, $ps_property=null) {
		$va_file_info = $this->get($ps_field, array('returnWithStructure' => true));
		if (!is_array($va_file_info) || !is_array($va_file_info = array_shift($va_file_info))) {
			return null;
		}
		
		if ($ps_property) { return isset($va_file_info[$ps_property]) ? $va_file_info[$ps_property] : null; }
		return $va_file_info;
	}
	# --------------------------------------------------------------------------------
	/**
	 * Clear file
	 * 
	 * @access public
	 * @param string $field field name
	 * @return bool always true
	 */
	public function clearFile($ps_field) {
		$this->_FILES_CLEAR[$ps_field] = 1;
		$this->_FIELD_VALUE_CHANGED[$ps_field] = 1;
		return true;
	}
	# --------------------------------------------------------------------------------
	/**
	 * Returns list of mimetypes of available conversions of files
	 * 
	 * @access public
	 * @param string $ps_field field name
	 * @return array
	 */ 
	public function getFileConversions($ps_field) {
		$va_file_info = $this->get($ps_field, array('returnWithStructure' => true));
		if (!is_array($va_file_info) || !is_array($va_file_info = array_shift($va_file_info))) {
			return array();
		}
		if (!is_array($va_file_info["CONVERSIONS"])) {
			return array();
		}
		return $va_file_info["CONVERSIONS"];
	}
	# --------------------------------------------------------------------------------
	/**
	 * Returns file path to converted version of file
	 * 
	 * @access public
	 * @param string $ps_field field name
	 * @param string $ps_format format of the converted version
	 * @return string file path
	 */ 
	public function getFileConversionPath($ps_field, $ps_format) {
		$va_file_info = $this->get($ps_field, array('returnWithStructure' => true));
		if (!is_array($va_file_info) || !is_array($va_file_info = array_shift($va_file_info))) {
			return null;
		}

		$vi = self::$_FILE_VOLUMES->getVolumeInformation($va_file_info["VOLUME"]);

		if (!is_array($vi)) {
			return "";
		}
		$va_conversions = $this->getFileConversions($ps_field);

		if ($va_conversions[$ps_format]) {
			return join("/",array($vi["absolutePath"], $va_file_info["HASH"], $va_file_info["MAGIC"]."_".$va_conversions[$ps_format]["FILENAME"]));
		} else {
			return "";
		}
	}
	# --------------------------------------------------------------------------------
	/**
	 * Returns url to converted version of file
	 * 
	 * @access public
	 * @param string $ps_field field name
	 * @param string $ps_format format of the converted version
	 * @return string url
	 */
	public function getFileConversionUrl($ps_field, $ps_format) {
		$va_file_info = $this->get($ps_field, array('returnWithStructure' => true));
		if (!is_array($va_file_info) || !is_array($va_file_info = array_shift($va_file_info))) {
			return null;
		}

		$vi = self::$_FILE_VOLUMES->getVolumeInformation($va_file_info["VOLUME"]);

		if (!is_array($vi)) {
			return "";
		}
		$va_conversions = $this->getFileConversions($ps_field);


		if ($va_conversions[$ps_format]) {
			return $vi["protocol"]."://".join("/", array($vi["hostname"], $vi["urlPath"], $va_file_info["HASH"], $va_file_info["MAGIC"]."_".$va_conversions[$ps_format]["FILENAME"]));
		} else {
			return "";
		}
	}
	# -------------------------------------------------------------------------------
	/**
	 * Generates filenames as follows: <table>_<field>_<primary_key>
	 * Makes the application die if no record is loaded
	 * 
	 * @access private
	 * @param string $field field name
	 * @return string file name
	 */
	public function _genFileName($field) {
		$pk = $this->getPrimaryKey();
		if ($pk) {
			return $this->TABLE."_".$field."_".$pk;
		} else {
			die("NO PK TO MAKE file name for $field!");
		}
	}
	# --------------------------------------------------------------------------------
	/**
	 * Processes uploaded files (only if something was uploaded)
	 * 
	 * @access private
	 * @param string $field field name
	 * @return string
	 */
	public function _processFiles($field) {
		$vs_sql = "";

		# only set file if something was uploaded
		# (ie. don't nuke an existing file because none
		#      was uploaded)
		if ((isset($this->_FILES_CLEAR[$field])) && ($this->_FILES_CLEAR[$field])) {
			#--- delete file
			@unlink($this->getFilePath($field));
			#--- delete conversions
			foreach ($this->getFileConversions($field) as $vs_format => $va_file_conversion) {
				@unlink($this->getFileConversionPath($field, $vs_format));
			}

			$this->_FILES[$field] = "";
			$this->_FIELD_VALUES[$field] = "";

			$vs_sql =  "$field = ".$this->quote(caSerializeForDatabase($this->_FILES[$field], true)).",";
		} else {
			$va_field_info = $this->getFieldInfo($field);
			if ((file_exists($this->_SET_FILES[$field]['tmp_name']))) {
				$ff = new File();
				$mimetype = $ff->divineFileFormat($this->_SET_FILES[$field]['tmp_name'], $this->_SET_FILES[$field]['original_filename']);

				if (is_array($va_field_info["FILE_FORMATS"]) && sizeof($va_field_info["FILE_FORMATS"]) > 0) {
					if (!in_array($mimetype, $va_field_info["FILE_FORMATS"])) {
						$this->postError(1605, _t("File is not a valid format"),"BaseModel->_processFiles()", $this->tableName().'.'.$field);
						return false;
					}
				}

				$vn_dangerous = 0;
				if (!$mimetype) {
					$mimetype = "application/octet-stream";
					$vn_dangerous = 1;
				}
				# get volume
				$vi = self::$_FILE_VOLUMES->getVolumeInformation($va_field_info["FILE_VOLUME"]);

				if (!is_array($vi)) {
					print "Invalid volume ".$va_field_info["FILE_VOLUME"]."<br>";
					exit;
				}

				if(!is_array($properties = $ff->getProperties())) { $properties = array(); }
				
				if ($properties['dangerous'] > 0) { $vn_dangerous = 1; }

				if (($dirhash = $this->_getDirectoryHash($vi["absolutePath"], $this->getPrimaryKey())) === false) {
					$this->postError(1600, _t("Could not create subdirectory for uploaded file in %1. Please ask your administrator to check the permissions of your media directory.", $vi["absolutePath"]),"BaseModel->_processFiles()", $this->tableName().'.'.$field);
					return false;
				}
				$magic = rand(0,99999);

				$va_pieces = explode("/", $this->_SET_FILES[$field]['original_filename']);
				$ext = array_pop($va_tmp = explode(".", array_pop($va_pieces)));
				if ($properties["dangerous"]) { $ext .= ".bin"; }
				if (!$ext) $ext = "bin";

				$filestem = $vi["absolutePath"]."/".$dirhash."/".$magic."_".$this->_genMediaName($field);
				$filepath = $filestem.".".$ext;


				$filesize = isset($properties["filesize"]) ? $properties["filesize"] : 0;
				if (!$filesize) {
					$properties["filesize"] = filesize($this->_SET_FILES[$field]['tmp_name']);
				}

				$file_desc = array(
					"FILE" => 1, # signifies is file
					"VOLUME" => $va_field_info["FILE_VOLUME"],
					"ORIGINAL_FILENAME" => $this->_SET_FILES[$field]['original_filename'],
					"MIMETYPE" => $mimetype,
					"FILENAME" => $this->_genMediaName($field).".".$ext,
					"HASH" => $dirhash,
					"MAGIC" => $magic,
					"PROPERTIES" => $properties,
					"DANGEROUS" => $vn_dangerous,
					"CONVERSIONS" => array(),
					"MD5" => md5_file($this->_SET_FILES[$field]['tmp_name'])
				);

				if (!@copy($this->_SET_FILES[$field]['tmp_name'], $filepath)) {
					$this->postError(1600, _t("File could not be copied. Ask your administrator to check permissions and file space for %1",$vi["absolutePath"]),"BaseModel->_processFiles()", $this->tableName().'.'.$field);
					return false;
				}


				# -- delete old file if its name is different from the one we just wrote (otherwise, we overwrote it)
				if ($filepath != $this->getFilePath($field)) {
					@unlink($this->getFilePath($field));
				}


				#
				# -- Attempt to do file conversions
				#
				if (isset($va_field_info["FILE_CONVERSIONS"]) && is_array($va_field_info["FILE_CONVERSIONS"]) && (sizeof($va_field_info["FILE_CONVERSIONS"]) > 0)) {
					foreach($va_field_info["FILE_CONVERSIONS"] as $vs_output_format) {
						if ($va_tmp = $ff->convert($vs_output_format, $filepath,$filestem)) { # new extension is added to end of stem by conversion
							$vs_file_ext = 			$va_tmp["extension"];
							$vs_format_name = 		$va_tmp["format_name"];
							$vs_long_format_name = 	$va_tmp["long_format_name"];
							$file_desc["CONVERSIONS"][$vs_output_format] = array(
								"MIMETYPE" => $vs_output_format,
								"FILENAME" => $this->_genMediaName($field)."_conv.".$vs_file_ext,
								"PROPERTIES" => array(
													"filesize" => filesize($filestem."_conv.".$vs_file_ext),
													"extension" => $vs_file_ext,
													"format_name" => $vs_format_name,
													"long_format_name" => $vs_long_format_name
												)
							);
						}
					}
				}

				$this->_FILES[$field] = $file_desc;
				$vs_sql =  "$field = ".$this->quote(caSerializeForDatabase($this->_FILES[$field], true)).",";
				$this->_FIELD_VALUES[$field]= $this->_SET_FILES[$field]  = $file_desc;
			}
		}
		return $vs_sql;
	}
}
