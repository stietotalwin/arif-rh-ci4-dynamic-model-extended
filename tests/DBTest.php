<?php namespace ArifrhFeatStieTotalWin\DynaModelTests;

use ArifrhFeatStieTotalWin\DynaModel\DB;
use ArifrhFeatStieTotalWin\DynaModelTests\DynaModelTestCase as TestCase;

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
        $this->expectException(\ArifrhFeatStieTotalWin\DynaModel\Exceptions\DBException::class);
        $categories = DB::table('categories');
    }
}
