<?php namespace StieTotalWin\DynaModelTests;

use StieTotalWin\DynaModel\DB;
use StieTotalWin\DynaModelTests\DynaModelTestCase as TestCase;

class DBTest extends TestCase
{
    /**
     * @return void
     */
    public function testDbtable()
    {
		$authors = DB::table('authors');
		$this->assertInstanceOf(\CodeIgniter\Model::class, $authors);
	}
    
    /**
     * @return void
     */
	public function testDbNonExistingTable()
    {
        $this->expectException(\StieTotalWin\DynaModel\Exceptions\DBException::class);
        $categories = DB::table('categories');
    }
}
