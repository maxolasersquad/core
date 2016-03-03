<?php
/**
 * @author Joas Schilling <nickvergessen@owncloud.com>
 * @author Robin Appelman <icewind@owncloud.com>
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\DAV\Tests\Unit\Connector\Sabre;

class Node extends \Test\TestCase {
	public function davPermissionsProvider() {
		return array(
			array(\OCP\Constants::PERMISSION_ALL, 'file', false, false, 'RDNVW'),
			array(\OCP\Constants::PERMISSION_ALL, 'dir', false, false, 'RDNVCK'),
			array(\OCP\Constants::PERMISSION_ALL, 'file', true, false, 'SRDNVW'),
			array(\OCP\Constants::PERMISSION_ALL, 'file', true, true, 'SRMDNVW'),
			array(\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_SHARE, 'file', true, false, 'SDNVW'),
			array(\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_UPDATE, 'file', false, false, 'RD'),
			array(\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_DELETE, 'file', false, false, 'RNVW'),
			array(\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_CREATE, 'file', false, false, 'RDNVW'),
			array(\OCP\Constants::PERMISSION_ALL - \OCP\Constants::PERMISSION_CREATE, 'dir', false, false, 'RDNV'),
		);
	}

	/**
	 * @dataProvider davPermissionsProvider
	 */
	public function testDavPermissions($permissions, $type, $shared, $mounted, $expected) {
		$info = $this->getMockBuilder('\OC\Files\FileInfo')
			->disableOriginalConstructor()
			->setMethods(array('getPermissions', 'isShared', 'isMounted', 'getType'))
			->getMock();
		$info->expects($this->any())
			->method('getPermissions')
			->will($this->returnValue($permissions));
		$info->expects($this->any())
			->method('isShared')
			->will($this->returnValue($shared));
		$info->expects($this->any())
			->method('isMounted')
			->will($this->returnValue($mounted));
		$info->expects($this->any())
			->method('getType')
			->will($this->returnValue($type));
		$view = $this->getMock('\OC\Files\View');

		$node = new  \OCA\DAV\Connector\Sabre\File($view, $info);
		$this->assertEquals($expected, $node->getDavPermissions());
	}

	public function sharePermissionsProvider() {
		return [
			[\OCP\Files\FileInfo::TYPE_FILE, false, false, false, false, 0],
			[\OCP\Files\FileInfo::TYPE_FILE, false, false, false,  true, 0],
			[\OCP\Files\FileInfo::TYPE_FILE, false, false,  true, false, 0],
			[\OCP\Files\FileInfo::TYPE_FILE, false, false,  true,  true, 0],
			[\OCP\Files\FileInfo::TYPE_FILE, false,  true, false, false, 0],
			[\OCP\Files\FileInfo::TYPE_FILE, false,  true, false,  true, 0],
			[\OCP\Files\FileInfo::TYPE_FILE, false,  true,  true, false, 0],
			[\OCP\Files\FileInfo::TYPE_FILE, false,  true,  true,  true, 0],
			[\OCP\Files\FileInfo::TYPE_FILE,  true, false, false, false, 17],
			[\OCP\Files\FileInfo::TYPE_FILE,  true, false, false,  true, 17],
			[\OCP\Files\FileInfo::TYPE_FILE,  true, false,  true, false, 19],
			[\OCP\Files\FileInfo::TYPE_FILE,  true, false,  true,  true, 19],
			[\OCP\Files\FileInfo::TYPE_FILE,  true,  true, false, false, 17],
			[\OCP\Files\FileInfo::TYPE_FILE,  true,  true, false,  true, 17],
			[\OCP\Files\FileInfo::TYPE_FILE,  true,  true,  true, false, 19],
			[\OCP\Files\FileInfo::TYPE_FILE,  true,  true,  true,  true, 19],
			[\OCP\Files\FileInfo::TYPE_FOLDER, false, false, false, false, 0],
			[\OCP\Files\FileInfo::TYPE_FOLDER, false, false, false,  true, 0],
			[\OCP\Files\FileInfo::TYPE_FOLDER, false, false,  true, false, 0],
			[\OCP\Files\FileInfo::TYPE_FOLDER, false, false,  true,  true, 0],
			[\OCP\Files\FileInfo::TYPE_FOLDER, false,  true, false, false, 0],
			[\OCP\Files\FileInfo::TYPE_FOLDER, false,  true, false,  true, 0],
			[\OCP\Files\FileInfo::TYPE_FOLDER, false,  true,  true, false, 0],
			[\OCP\Files\FileInfo::TYPE_FOLDER, false,  true,  true,  true, 0],
			[\OCP\Files\FileInfo::TYPE_FOLDER,  true, false, false, false, 17],
			[\OCP\Files\FileInfo::TYPE_FOLDER,  true, false, false,  true, 25],
			[\OCP\Files\FileInfo::TYPE_FOLDER,  true, false,  true, false, 19],
			[\OCP\Files\FileInfo::TYPE_FOLDER,  true, false,  true,  true, 27],
			[\OCP\Files\FileInfo::TYPE_FOLDER,  true,  true, false, false, 21],
			[\OCP\Files\FileInfo::TYPE_FOLDER,  true,  true, false,  true, 29],
			[\OCP\Files\FileInfo::TYPE_FOLDER,  true,  true,  true, false, 23],
			[\OCP\Files\FileInfo::TYPE_FOLDER,  true,  true,  true,  true, 31],
		];
	}

	/**
	 * @dataProvider sharePermissionsProvider
	 */
	public function testSharePermissions($type, $canShare, $canCreate, $canChange, $canDelete, $expected) {
		$storage = $this->getMock('\OCP\Files\Storage');
		$storage->method('isSharable')->willReturn($canShare);
		$storage->method('isCreatable')->willReturn($canCreate);
		$storage->method('isUpdatable')->willReturn($canChange);
		$storage->method('isDeletable')->willReturn($canDelete);

		$info = $this->getMockBuilder('\OC\Files\FileInfo')
			->disableOriginalConstructor()
			->setMethods(array('getStorage', 'getType'))
			->getMock();

		$info->method('getStorage')->willReturn($storage);
		$info->method('getType')->willReturn($type);

		$view = $this->getMock('\OC\Files\View');

		$node = new  \OCA\DAV\Connector\Sabre\File($view, $info);
		$this->assertEquals($expected, $node->getSharePermissions());
	}
}
