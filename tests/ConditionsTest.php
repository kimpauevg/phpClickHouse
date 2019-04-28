<?php

namespace ClickHouseDB\Tests;
use PHPUnit\Framework\TestCase;

/**
 * @group ConditionsTest
 */
final class ConditionsTest extends TestCase
{
    use WithClient;

    private function getInputParams()
    {
        return [
            'lastdays'=>3,
            'null'=>null,
            'false'=>false,
            'true'=>true,
            'zero'=>0,
            's_false'=>'false',
            's_null'=>'null',
            's_empty'=>'',
            'int30'=>30,
            'int1'=>1,
            'str0'=>'0',
            'str1'=>'1'
        ];
    }

    private function condTest($sql,$equal)
    {
        $input_params=$this->getInputParams();
        $this->assertEquals($equal,$this->client->selectAsync($sql, $input_params)->sql());
    }
    /**
     *
     */
    public function testSqlConditionsBig()
    {


        $select="
            1: {if ll}NOT_SHOW{else}OK{/if}{if ll}NOT_SHOW{else}OK{/if}
            2: {if null}NOT_SHOW{else}OK{/if} 
            3: {if qwert}NOT_SHOW{/if}
            4: {ifset zero} NOT_SHOW {else}OK{/if}
            5: {ifset false} NOT_SHOW {/if}
            6: {ifset s_false} OK {/if}
            7: {ifint zero} NOT_SHOW {/if}
            8: {if zero}OK{/if}
            9: {ifint s_empty}NOT_SHOW{/if}
            0: {ifint s_null}NOT_SHOW{/if}
            1: {ifset null} NOT_SHOW {/if}
            
            
            INT: 
            0: {ifint zero} NOT_SHOW {/if}
            1: {ifint int1} OK {/if}
            2: {ifint int30} OK {/if}
            3: {ifint int30}OK {else} NOT_SHOW {/if}
            4: {ifint str0} NOT_SHOW {else}OK{/if}
            5: {ifint str1} OK {else} NOT_SHOW {/if}
            6: {ifint int30} OK {else} NOT_SHOW {/if}
            7: {ifint s_empty} NOT_SHOW {else} OK {/if}
            8: {ifint true} OK {else} NOT_SHOW {/if}
            
            STRING:
            0: {ifstring s_empty}NOT_SHOW{else}OK{/if}
            1: {ifstring s_null}OK{else}NOT_SHOW{/if}
            
            BOOL:
            1: {ifbool int1}NOT_SHOW{else}OK{/if}
            2: {ifbool int30}NOT_SHOW{else}OK{/if}
            3: {ifbool zero}NOT_SHOW{else}OK{/if}
            4: {ifbool false}NOT_SHOW{else}OK{/if}
            5: {ifbool true}OK{else}NOT_SHOW{/if}
            5: {ifbool true}OK{/if}
            6: {ifbool false}OK{/if}
            
            
            0: {if s_empty}
            
            
            SHOW
            
            
            {/if}
            
            {ifint lastdays}
            
            
                event_date>=today()-{lastdays}
            
            
            {else}
            
            
                event_date>=today()
            
            
            {/if}
        ";


        $this->restartClickHouseClient();
        $this->client->enableQueryConditions();
        $input_params=$this->getInputParams();
        $this->assertNotContains(
            'NOT_SHOW',$this->client->selectAsync($select, $input_params)->sql()
        );


    }
    public function testSqlConditions1()
    {
        $this->restartClickHouseClient();
        $this->client->enableQueryConditions();

        $this->condTest('{ifint s_empty}NOT_SHOW{/if}{ifbool int1}NOT_SHOW{else}OK{/if}{ifbool int30}NOT_SHOW{else}OK{/if}','OKOK FORMAT JSON');
        $this->condTest('{ifbool false}OK{/if}{ifbool true}OK{/if}{ifbool true}OK{else}NOT_SHOW{/if}','OKOK FORMAT JSON');
        $this->condTest('{ifstring s_empty}NOT_SHOW{else}OK{/if}{ifstring s_null}OK{else}NOT_SHOW{/if}','OKOK FORMAT JSON');
        $this->condTest('{ifint int1} OK {/if}',' OK FORMAT JSON');
        $this->condTest('{ifint s_empty}NOT_SHOW{/if}_1_','_1_ FORMAT JSON');
        $this->condTest('1_{ifint str0} NOT_SHOW {else}OK{/if}_2','1_OK_2 FORMAT JSON');
        $this->condTest('1_{if zero}OK{/if}_2','1_OK_2 FORMAT JSON');
        $this->condTest('1_{if empty}OK{/if}_2','1__2 FORMAT JSON');
        $this->condTest('1_{if s_false}OK{/if}_2','1_OK_2 FORMAT JSON');
        $this->condTest('1_{if qwert}NOT_SHOW{/if}_2','1__2 FORMAT JSON');
        $this->condTest('1_{ifset zero} NOT_SHOW {else}OK{/if}{ifset false} NOT_SHOW {/if}{ifset s_false} OK {/if}_2','1_OK OK_2 FORMAT JSON');
        $this->condTest('1_{ifint zero} NOT_SHOW {/if}{if zero}OK{/if}{ifint s_empty}NOT_SHOW{/if}_2','1_OK_2 FORMAT JSON');
        $this->condTest('1_{ifint s_null}NOT_SHOW{/if}{ifset null} NOT_SHOW {/if}_2','1__2 FORMAT JSON');




    }
    public function testSqlConditions()
    {
        $input_params = [
            'select_date' => ['2000-10-10', '2000-10-11', '2000-10-12'],
            'limit'       => 5,
            'from_table'  => 'table_x_y',
            'idid'        => 0,
            'false'       => false
        ];

        $this->assertEquals(
            'SELECT * FROM table_x_y FORMAT JSON',
            $this->client->selectAsync('SELECT * FROM {from_table}', $input_params)->sql()
        );

        $this->assertEquals(
            'SELECT * FROM table_x_y WHERE event_date IN (\'2000-10-10\',\'2000-10-11\',\'2000-10-12\') FORMAT JSON',
            $this->client->selectAsync('SELECT * FROM {from_table} WHERE event_date IN (:select_date)', $input_params)->sql()
        );

        $this->client->enableQueryConditions();

        $this->assertEquals(
            'SELECT * FROM ZZZ LIMIT 5 FORMAT JSON',
            $this->client->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', $input_params)->sql()
        );

        $this->assertEquals(
            'SELECT * FROM ZZZ NOOPE FORMAT JSON',
            $this->client->selectAsync('SELECT * FROM ZZZ {if nope}LIMIT {limit}{else}NOOPE{/if}', $input_params)->sql()
        );
        $this->assertEquals(
            'SELECT * FROM 0 FORMAT JSON',
            $this->client->selectAsync('SELECT * FROM :idid', $input_params)->sql()
        );


        $this->assertEquals(
            'SELECT * FROM  FORMAT JSON',
            $this->client->selectAsync('SELECT * FROM :false', $input_params)->sql()
        );



        $isset=[
            'FALSE'=>false,
            'ZERO'=>0,
            'NULL'=>null

        ];

        $this->assertEquals(
            '|ZERO||  FORMAT JSON',
            $this->client->selectAsync('{if FALSE}FALSE{/if}|{if ZERO}ZERO{/if}|{if NULL}NULL{/if}| ' ,$isset)->sql()
        );



    }


    public function testSqlDisableConditions()
    {
        $this->assertEquals('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if} FORMAT JSON',  $this->client->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', [])->sql());
        $this->assertEquals('SELECT * FROM ZZZ {if limit}LIMIT 123{/if} FORMAT JSON',  $this->client->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', ['limit'=>123])->sql());
        $this->client->cleanQueryDegeneration();
        $this->assertEquals('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if} FORMAT JSON',  $this->client->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', ['limit'=>123])->sql());
        $this->restartClickHouseClient();
        $this->assertEquals('SELECT * FROM ZZZ {if limit}LIMIT 123{/if} FORMAT JSON',  $this->client->selectAsync('SELECT * FROM ZZZ {if limit}LIMIT {limit}{/if}', ['limit'=>123])->sql());


    }
}