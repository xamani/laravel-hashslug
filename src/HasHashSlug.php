<?php

/*

Laravel HashSlug: Package providing a trait to use Hashids on a model
Copyright (C) 2017  Balázs Dura-Kovács

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

*/

namespace Balping\HashSlug;

trait HasHashSlug {
	/**
	 * Cached hashslug
	 * @var null|string
	 */
	private $slug = null;

	/**
	 * Cached HashIds instance
	 * @var null|\Hashids\Hashids
	 */
	private static $hashIds = null;

	/**
	 * Returns a chached Hashids instanse
	 * or initialises it with salt
	 * 
	 * @return \Hashids\Hashids
	 */
	private static function getHashids(){
		if (is_null(static::$hashIds)){

			$minSlugLength = 5;
			if(isset(static::$minSlugLength)) {
				$minSlugLength = static::$minSlugLength;
			}

			if(isset(static::$modelSalt)) {
				$modelSalt = static::$modelSalt;
			}else{
				$modelSalt = get_called_class();
			}

			if(isset(static::$alphabet)) {
				$alphabet = static::$alphabet;
			}else{
				$alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
			}

			$salt = config('hashslug.appsalt', env('APP_KEY')) . $modelSalt;
			
			// This is impotant!
			// Don't use a weak hash, otherwise
			// your app key can be exposed
			// http://carnage.github.io/2015/08/cryptanalysis-of-hashids
			$salt = hash('sha256', $salt);

			static::$hashIds = new \Hashids\Hashids($salt, $minSlugLength, $alphabet);
		}

		return static::$hashIds;
	}

	/**
	 * Hashslug calculated from id
	 * @return string
	 */
	public function slug(){
		if (is_null($this->slug)){
			$hashids = $this->getHashids();

			$this->slug = $hashids->encode($this->{$this->getKeyName()});
		}

		return $this->slug;
	}

	public function getRouteKeyName(){
		return 'hashslug';
	}

	public function getRouteKey() {
		return $this->slug();
	}

	/**
	 * Terrible hack to make automatic implicit route model binding possible
	 *
	 * @see \Illuminate\Routing\RouteBinding@forModel
	 * 
     * @param  string|array|\Closure  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder
     */
	public function where(... $arguments){
		if($arguments[0] == 'hashslug'){
			$id = static::decodeSlug($arguments[1]);
			return parent::where($this->getKeyName(), $id);
		}else{
			return parent::where(... $arguments);
		}
	}

	/**
	 * Decodes slug to id
	 * @param  string $slug
	 * @return int|null
	 */
	private static function decodeSlug($slug){
		$hashids = static::getHashids();

		$decoded = $hashids->decode($slug);

		if(! isset($decoded[0])){
			return null;
		}

		return (int) $decoded[0];
	}

	/**
	 * Wrapper around Model::findOrFail
	 * 
	 * @param  string $slug
	 * @return \Illuminate\Database\Eloquent\Model
	 */
	public static function findBySlugOrFail($slug){
		$id = static::decodeSlug($slug);

		return static::findOrFail($id);
	}

	/**
	 * Wrapper around Model::find
	 * 
	 * @param  string $slug
	 * @return \Illuminate\Database\Eloquent\Model|null
	 */
	public static function findBySlug($slug){
		$id = static::decodeSlug($slug);

		return static::find($id);
	}
}
