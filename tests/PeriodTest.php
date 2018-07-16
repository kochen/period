<?php

/**
 * League.Uri (https://period.thephpleague.com).
 *
 * @author  Ignace Nyamagana Butera <nyamsprod@gmail.com>
 * @license https://github.com/thephpleague/period/blob/master/LICENSE (MIT License)
 * @version 4.0.0
 * @link    https://github.com/thephpleague/period
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace League\Period\Test;

use DateInterval;
use DatePeriod;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use League\Period\Exception;
use League\Period\Period;
use PHPUnit\Framework\TestCase as TestCase;
use TypeError;

class PeriodTest extends TestCase
{
    /**
     * @var string
     */
    private $timezone;

    public function setUp()
    {
        $this->timezone = date_default_timezone_get();
    }

    public function tearDown()
    {
        date_default_timezone_set($this->timezone);
    }

    public function testToString()
    {
        date_default_timezone_set('Africa/Nairobi');
        $period = new Period('2014-05-01', '2014-05-08');
        $res = (string) $period;
        $this->assertContains('2014-04-30T21:00:00', $res);
        $this->assertContains('2014-05-07T21:00:00', $res);
    }

    public function testJsonSerialize()
    {
        $period = Period::createFromMonth(2015, 4);
        $json = json_encode($period);
        $this->assertInternalType('string', $json);
        $res = json_decode($json);

        $this->assertEquals($period->getStartDate(), new DateTimeImmutable($res->startDate));
        $this->assertEquals($period->getEndDate(), new DateTimeImmutable($res->endDate));
    }

    /**
     * @dataProvider provideGetDatePeriodData
     * @param mixed $interval
     * @param mixed $option
     * @param mixed $count
     */
    public function testGetDatePeriod($interval, $option, $count)
    {
        $period = Period::createFromDuration(new DateTime('2012-01-12'), '1 DAY');
        $range = $period->getDatePeriod($interval, $option);
        $this->assertInstanceOf(DatePeriod::class, $range);
        $this->assertCount($count, iterator_to_array($range));
    }

    public function provideGetDatePeriodData()
    {
        return [
            'useDateInterval' => [new DateInterval('PT1H'), 0, 24],
            'useString' => ['2 HOUR', 0, 12],
            'useInt' => [9600, 0, 9],
            'useFloat' => [14400.0, 0, 6],
            'exclude start date useDateInterval' => [new DateInterval('PT1H'), DatePeriod::EXCLUDE_START_DATE, 23],
            'exclude start date useString' => ['2 HOUR', DatePeriod::EXCLUDE_START_DATE, 11],
            'exclude start date useInt' => [9600, DatePeriod::EXCLUDE_START_DATE, 8],
            'exclude start date useFloat' => [14400.0, DatePeriod::EXCLUDE_START_DATE, 5],
        ];
    }

    public function testCreateFromDatePeriod()
    {
        $datePeriod = new DatePeriod(
            new DateTime('2016-05-16T00:00:00Z'),
            new DateInterval('P1D'),
            new DateTime('2016-05-20T00:00:00Z')
        );
        $period = Period::createFromDatePeriod($datePeriod);
        $this->assertEquals($datePeriod->getStartDate(), $period->getStartDate());
        $this->assertEquals($datePeriod->getEndDate(), $period->getEndDate());
    }

    public function testCreateFromDatePeriodThrowsException()
    {
        $this->expectException(Exception::class);
        $datePeriod = new DatePeriod('R4/2012-07-01T00:00:00Z/P7D');
        Period::createFromDatePeriod($datePeriod);
    }

    public function testConstructorThrowTypeError()
    {
        $this->expectException(TypeError::class);
        new Period(new DateTime(), []);
    }

    public function testGetDateInterval()
    {
        $period = Period::createFromMonth(2014, 3);
        $this->assertInstanceOf(DateInterval::class, $period->getDateInterval());
    }

    public function testGetTimestampInterval()
    {
        $period = Period::createFromMonth(2014, 3);
        $this->assertInternalType('float', $period->getTimestampInterval());
    }

    public function testSplit()
    {
        $period = Period::createFromDuration(new DateTime('2012-01-12'), '1 DAY');
        $range = $period->split(3600);
        foreach ($range as $innerPeriod) {
            $this->assertInstanceOf(Period::class, $innerPeriod);
        }
    }

    public function testSplitMustRecreateParentObject()
    {
        $period = Period::createFromDuration(new DateTime('2012-01-12'), '1 DAY');
        $range  = $period->split(3600);
        $total = null;
        foreach ($range as $part) {
            if (is_null($total)) {
                $total = $part;
                continue;
            }
            $total = $total->merge($part);
        }
        $this->assertEquals($period, $total);
    }


    public function testSplitWithLargeInterval()
    {
        $period = Period::createFromDuration(new DateTime('2012-01-12'), '1 DAY');
        $range  = $period->split('2 DAY');
        foreach ($range as $expectedPeriod) {
            $this->assertEquals($period, $expectedPeriod);
        }
    }

    public function testSplitWithInconsistentInterval()
    {
        $period = Period::createFromDuration(new DateTime('2012-01-12'), '1 DAY');
        $range = [];
        foreach ($period->split('10 HOURS') as $innerPeriod) {
            $range[] = $innerPeriod;
        }
        $last = array_pop($range);
        $this->assertEquals(14400, $last->getTimestampInterval());
    }

    public function testSplitDataBackwards()
    {
        $period = Period::createFromDuration(new DateTime('2015-01-01'), '3 days');
        $range = $period->splitBackwards('1 day');
        $list = [];
        foreach ($range as $innerPeriod) {
            $list[] = $innerPeriod;
        }

        $result = array_map(function (Period $range) {
            return [
                'start' => $range->getStartDate()->format('Y-m-d H:i:s'),
                'end'   => $range->getEndDate()->format('Y-m-d H:i:s'),
            ];
        }, $list);

        $expected = [
            [
                'start' => '2015-01-03 00:00:00',
                'end'   => '2015-01-04 00:00:00',
            ],
            [
                'start' => '2015-01-02 00:00:00',
                'end'   => '2015-01-03 00:00:00',
            ],
            [
                'start' => '2015-01-01 00:00:00',
                'end'   => '2015-01-02 00:00:00',
            ],
        ];
        $this->assertSame($expected, $result);
    }

    public function testSplitBackwardsWithInconsistentInterval()
    {
        $period = Period::createFromDuration('2010-01-01', '1 DAY');
        $range = [];
        foreach ($period->splitBackwards('10 HOURS') as $innerPeriod) {
            $range[] = $innerPeriod;
        }

        $last = array_pop($range);
        $this->assertEquals(14400, $last->getTimestampInterval());
    }

    public function testSetState()
    {
        $period = new Period('2014-05-01', '2014-05-08');
        $generatedPeriod = eval('return '.var_export($period, true).';');
        $this->assertTrue($generatedPeriod->sameValueAs($period));
    }

    public function testConstructor()
    {
        $period = new Period('2014-05-01', '2014-05-08');
        $start = $period->getStartDate();
        $this->assertEquals(new DateTimeImmutable('2014-05-01'), $start);
        $this->assertEquals(new DateTimeImmutable('2014-05-08'), $period->getEndDate());
        $this->assertInstanceOf(DateTimeImmutable::class, $start);
    }

    public function testConstructorWithMicroSecondsSucceed()
    {
        $period = new Period('2014-05-01 00:00:00', '2014-05-01 00:00:00');
        $this->assertEquals(new DateInterval('PT0S'), $period->getDateInterval());
    }

    public function testConstructorThrowException()
    {
        $this->expectException(Exception::class);
        new Period(
            new DateTime('2014-05-01', new DateTimeZone('Europe/Paris')),
            new DateTime('2014-05-01', new DateTimeZone('Africa/Nairobi'))
        );
    }

    public function testConstructorWithDateTimeInterface()
    {
        $period = new Period('2014-05-01', new DateTime('2014-05-08'));
        $this->assertInstanceOf(DateTimeImmutable::class, $period->getEndDate());
        $this->assertInstanceOf(DateTimeImmutable::class, $period->getStartDate());
    }

    /**
     * @dataProvider provideCreateFromDurationData
     * @param mixed $startDate
     * @param mixed $endDate
     * @param mixed $duration
     */
    public function testCreateFromDuration($startDate, $endDate, $duration)
    {
        $period = Period::createFromDuration($startDate, $duration);
        $this->assertEquals(new DateTimeImmutable($startDate), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable($endDate), $period->getEndDate());
    }

    public function provideCreateFromDurationData()
    {
        return [
            'usingAString' => [
                '2015-01-01', '2015-01-02', '+1 DAY',
            ],
            'usingAnInt' => [
                '2015-01-01 10:00:00', '2015-01-01 11:00:00', 3600,
            ],
            'usingADateInterval' => [
                '2015-01-01 10:00:00', '2015-01-01 11:00:00', new DateInterval('PT1H'),
            ],
            'usingAFloatWithNoMicroseconds' => [
                '2015-01-01 10:00:00', '2015-01-01 11:00:00', 3600.0,
            ],
        ];
    }

    public function testCreateFromDurationWithInvalidInteger()
    {
        $this->expectException(\Exception::class);
        Period::createFromDuration('2014-01-01', -1);
    }

    public function testCreateFromDurationFailedWithOutofRangeInterval()
    {
        $this->expectException(Exception::class);
        Period::createFromDuration(new DateTime('2012-01-12'), '-1 DAY');
    }

    public function testCreateFromDurationFailedWithInvalidInterval()
    {
        $this->expectException(TypeError::class);
        Period::createFromDuration(new DateTime('2012-01-12'), []);
    }

    /**
     * @dataProvider provideCreateFromDurationBeforeEndData
     * @param mixed $startDate
     * @param mixed $endDate
     * @param mixed $duration
     */
    public function testCreateFromDurationBeforeEnd($startDate, $endDate, $duration)
    {
        $period = Period::createFromDurationBeforeEnd($endDate, $duration);
        $this->assertEquals(new DateTimeImmutable($startDate), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable($endDate), $period->getEndDate());
    }

    public function provideCreateFromDurationBeforeEndData()
    {
        return [
            'usingAString' => [
                '2015-01-01', '2015-01-02', '+1 DAY',
            ],
            'usingAnInt' => [
                '2015-01-01 10:00:00', '2015-01-01 11:00:00', 3600,
            ],
            'usingADateInterval' => [
                '2015-01-01 10:00:00', '2015-01-01 11:00:00', new DateInterval('PT1H'),
            ],
        ];
    }

    public function testCreateFromDurationBeforeEndFailedWithOutofRangeInterval()
    {
        $this->expectException(Exception::class);
        Period::createFromDurationBeforeEnd(new DateTime('2012-01-12'), '-1 DAY');
    }

    public function testCreateFromWeek()
    {
        $period = Period::createFromWeek(2014, 3);
        $this->assertEquals(new DateTimeImmutable('2014-01-13'), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2014-01-20'), $period->getEndDate());
    }

    public function testCreateFromWeekFailedWithLowInvalidIndex()
    {
        $this->expectException(Exception::class);
        Period::createFromWeek(2014, 0);
    }

    public function testCreateFromWeekFailedWithHighInvalidIndex()
    {
        $this->expectException(Exception::class);
        Period::createFromWeek(2014, 54);
    }

    public function testCreateFromWeekFailedWithInvalidYearIndex()
    {
        $this->expectException(TypeError::class);
        Period::createFromWeek([], 1);
    }

    public function testCreateFromWeekFailedWithMissingSemesterValue()
    {
        $this->expectException(Exception::class);
        Period::createFromWeek(2014, null);
    }

    public function testCreateFromMonth()
    {
        $period = Period::createFromMonth(2014, 3);
        $this->assertEquals(new DateTimeImmutable('2014-03-01'), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2014-04-01'), $period->getEndDate());
    }

    public function testCreateFromMonthFailedWithHighInvalidIndex()
    {
        $this->expectException(Exception::class);
        Period::createFromMonth(2014, 13);
    }

    public function testCreateFromMonthFailedWithLowInvalidIndex()
    {
        $this->expectException(Exception::class);
        Period::createFromMonth(2014, 0);
    }

    public function testCreateFromMonthFailedWithInvalidYearIndex()
    {
        $this->expectException(TypeError::class);
        Period::createFromMonth([], 1);
    }

    public function testCreateFromMonthFailedWithMissingSemesterValue()
    {
        $this->expectException(Exception::class);
        Period::createFromMonth(2014, null);
    }

    public function testCreateFromQuarter()
    {
        $period = Period::createFromQuarter(2014, 3);
        $this->assertEquals(new DateTimeImmutable('2014-07-01'), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2014-10-01'), $period->getEndDate());
    }

    public function testCreateFromQuarterFailedWithHighInvalidIndex()
    {
        $this->expectException(Exception::class);
        Period::createFromQuarter(2014, 5);
    }

    public function testCreateFromQuarterFailedWithLowInvalidIndex()
    {
        $this->expectException(Exception::class);
        Period::createFromQuarter(2014, 0);
    }

    public function testCreateFromQuarterFailedWithInvalidYearIndex()
    {
        $this->expectException(TypeError::class);
        Period::createFromQuarter([], 1);
    }

    public function testCreateFromQuarterFailedWithMissingSemesterValue()
    {
        $this->expectException(Exception::class);
        Period::createFromQuarter(2014, null);
    }

    public function testCreateFromSemester()
    {
        $period = Period::createFromSemester(2014, 2);
        $this->assertEquals(new DateTimeImmutable('2014-07-01'), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2015-01-01'), $period->getEndDate());
    }

    public function testCreateFromSemesterFailedWithInvalidYearIndex()
    {
        $this->expectException(TypeError::class);
        Period::createFromSemester([], 1);
    }

    public function testCreateFromSemesterFailedWithMissingSemesterValue()
    {
        $this->expectException(Exception::class);
        Period::createFromSemester(2014, null);
    }

    public function testCreateFromSemesterFailedWithLowInvalidIndex()
    {
        $this->expectException(Exception::class);
        Period::createFromSemester(2014, 0);
    }

    public function testCreateFromSemesterFailedWithHighInvalidIndex()
    {
        $this->expectException(Exception::class);
        Period::createFromSemester(2014, 3);
    }

    public function testCreateFromYear()
    {
        $period = Period::createFromYear(2014);
        $this->assertEquals(new DateTimeImmutable('2014-01-01'), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2015-01-01'), $period->getEndDate());
    }

    public function testCreateFromDay()
    {
        $period = Period::createFromDay(new ExtendedDate('2008-07-01T22:35:17.123456+08:00'));
        $this->assertEquals(new DateTimeImmutable('2008-07-01T00:00:00+08:00'), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2008-07-02T00:00:00+08:00'), $period->getEndDate());
        $this->assertEquals('+08:00', $period->getStartDate()->format('P'));
        $this->assertEquals('+08:00', $period->getEndDate()->format('P'));
        $this->assertInstanceOf(ExtendedDate::class, $period->getStartDate());
        $this->assertInstanceOf(ExtendedDate::class, $period->getEndDate());
    }

    public function testCreateFromHour()
    {
        $today = new ExtendedDate('2008-07-01T22:35:17.123456+08:00');
        $period = Period::createFromHour($today);
        $this->assertEquals(new DateTimeImmutable('2008-07-01T22:00:00+08:00'), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2008-07-01T23:00:00+08:00'), $period->getEndDate());
        $this->assertEquals('+08:00', $period->getStartDate()->format('P'));
        $this->assertEquals('+08:00', $period->getEndDate()->format('P'));
        $this->assertInstanceOf(ExtendedDate::class, $period->getStartDate());
        $this->assertInstanceOf(ExtendedDate::class, $period->getEndDate());
    }

    public function testCreateFromMinute()
    {
        $today = new ExtendedDate('2008-07-01T22:35:17.123456+08:00');
        $period = Period::createFromMinute($today);
        $this->assertEquals(new DateTimeImmutable('2008-07-01T22:35:00+08:00'), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2008-07-01T22:36:00+08:00'), $period->getEndDate());
        $this->assertEquals('+08:00', $period->getStartDate()->format('P'));
        $this->assertEquals('+08:00', $period->getEndDate()->format('P'));
        $this->assertInstanceOf(ExtendedDate::class, $period->getStartDate());
        $this->assertInstanceOf(ExtendedDate::class, $period->getEndDate());
    }

    public function testCreateFromSecond()
    {
        $today = new ExtendedDate('2008-07-01T22:35:17.123456+08:00');
        $period = Period::createFromSecond($today);
        $this->assertEquals(new DateTimeImmutable('2008-07-01T22:35:17+08:00'), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2008-07-01T22:35:18+08:00'), $period->getEndDate());
        $this->assertEquals('+08:00', $period->getStartDate()->format('P'));
        $this->assertEquals('+08:00', $period->getEndDate()->format('P'));
        $this->assertInstanceOf(ExtendedDate::class, $period->getStartDate());
        $this->assertInstanceOf(ExtendedDate::class, $period->getEndDate());
    }

    public function testCreateFromWithDateTimeInterface()
    {
        $this->assertTrue(Period::createFromWeek('2008W27')->sameValueAs(Period::createFromWeek(2008, 27)));
        $this->assertTrue(Period::createFromMonth('2008-07')->sameValueAs(Period::createFromMonth(2008, 7)));
        $this->assertTrue(Period::createFromQuarter('2008-02')->sameValueAs(Period::createFromQuarter(2008, 1)));
        $this->assertTrue(Period::createFromSemester('2008-10')->sameValueAs(Period::createFromSemester(2008, 2)));
        $this->assertTrue(Period::createFromYear('2008-01')->sameValueAs(Period::createFromYear(2008)));
    }

    public function testCreateFromMonthWithDateTimeInterface()
    {
        $today = new ExtendedDate('2008-07-01T22:35:17.123456+08:00');
        $period = Period::createFromMonth($today);
        $this->assertEquals(new DateTimeImmutable('2008-07-01T00:00:00+08:00'), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2008-08-01T00:00:00+08:00'), $period->getEndDate());
        $this->assertEquals('+08:00', $period->getStartDate()->format('P'));
        $this->assertEquals('+08:00', $period->getEndDate()->format('P'));
        $this->assertInstanceOf(ExtendedDate::class, $period->getStartDate());
        $this->assertInstanceOf(ExtendedDate::class, $period->getEndDate());
    }

    public function testCreateFromYearWithDateTimeInterface()
    {
        $today = new ExtendedDate('2008-07-01T22:35:17.123456+08:00');
        $period = Period::createFromYear($today);
        $this->assertEquals(new DateTimeImmutable('2008-01-01T00:00:00+08:00'), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2009-01-01T00:00:00+08:00'), $period->getEndDate());
        $this->assertEquals('+08:00', $period->getStartDate()->format('P'));
        $this->assertEquals('+08:00', $period->getEndDate()->format('P'));
        $this->assertInstanceOf(ExtendedDate::class, $period->getStartDate());
        $this->assertInstanceOf(ExtendedDate::class, $period->getEndDate());
    }

    public function testIsBeforeDatetime()
    {
        $orig = Period::createFromDuration('2012-01-01', '1 MONTH');
        $this->assertTrue($orig->isBefore(new DateTime('2015-01-01')));
        $this->assertFalse($orig->isBefore(new DateTime('2010-01-01')));
    }

    public function testIsBeforePeriod()
    {
        $orig = Period::createFromDuration('2012-01-01', '1 MONTH');
        $alt  = Period::createFromDuration('2012-04-01', '2 MONTH');
        $this->assertTrue($orig->isBefore($alt));
        $this->assertFalse($alt->isBefore($orig));
    }

    public function testIsBeforePeriodWithAbutsPeriods()
    {
        $orig = Period::createFromDuration('2012-01-01', '1 MONTH');
        $this->assertTrue($orig->isBefore(Period::createFromDuration('2012-02-01', new DateInterval('PT1H'))));
    }

    public function testIsAfterDatetime()
    {
        $orig = Period::createFromDuration('2012-01-01', '1 MONTH');
        $this->assertFalse($orig->isAfter(new DateTime('2015-01-01')));
        $this->assertTrue($orig->isAfter(new DateTime('2010-01-01')));
    }

    public function testIsAfterPeriod()
    {
        $orig = Period::createFromDuration('2012-01-01', '1 MONTH');
        $alt  = Period::createFromDuration('2012-04-01', '2 MONTH');
        $this->assertFalse($orig->isAfter($alt));
        $this->assertTrue($alt->isAfter($orig));
    }

    public function testIsAfterDatetimeAbuts()
    {
        $orig = Period::createFromDuration('2012-01-01', '1 MONTH');
        $this->assertTrue($orig->isBefore($orig->getEndDate()));
        $this->assertFalse($orig->isAfter($orig->getStartDate()));
    }

    /**
     * @dataProvider provideAbutsData
     * @param Period $period
     * @param Period $arg
     * @param mixed  $expected
     */
    public function testAbuts(Period $period, Period $arg, $expected)
    {
        $this->assertSame($expected, $period->abuts($arg));
    }

    public function provideAbutsData()
    {
        return [
            'testAbutsReturnsTrueWithEqualDatePoints' => [
                Period::createFromDuration('2012-01-01', '1 MONTH'),
                Period::createFromDuration('2012-02-01', '2 MONTH'),
                true,
            ],
            'testAbutsReturnsFalseWithoutEqualDatePoints' => [
                Period::createFromDuration('2012-01-01', '1 MONTH'),
                Period::createFromDuration('2012-01-01', '2 MONTH'),
                false,
            ],
        ];
    }

    /**
     * @dataProvider provideOverlapsData
     * @param Period $period
     * @param Period $arg
     * @param mixed  $expected
     */
    public function testOverlaps(Period $period, Period $arg, $expected)
    {
        $this->assertSame($expected, $period->overlaps($arg));
    }

    public function provideOverlapsData()
    {
        return [
            'testOverlapsReturnsFalseWithAbutsPeriods' => [
                Period::createFromMonth(2014, 3),
                Period::createFromMonth(2014, 4),
                false,
            ],
            'testContainsReturnsFalseWithGappedPeriods' => [
                Period::createFromMonth(2014, 3),
                Period::createFromMonth(2013, 4),
                false,
            ],
            'testOverlapsReturnsTrue' => [
                Period::createFromMonth(2014, 3),
                Period::createFromDuration('2014-03-15', '3 WEEKS'),
                true,
            ],
            'testOverlapsReturnsTureWithSameDatepointsPeriods' => [
                Period::createFromMonth(2014, 3),
                new Period('2014-03-01', '2014-04-01'),
                true,
            ],
            'testOverlapsReturnsTrueContainedPeriods' => [
                Period::createFromMonth(2014, 3),
                Period::createFromDuration('2014-03-13', '2014-03-15'),
                true,
            ],
            'testOverlapsReturnsTrueContainedPeriodsBackward' => [
                Period::createFromDuration('2014-03-13', '2014-03-15'),
                Period::createFromMonth(2014, 3),
                true,
            ],
        ];
    }

    /**
     * @dataProvider provideContainsData
     * @param Period $period
     * @param mixed  $arg
     * @param mixed  $expected
     */
    public function testContains(Period $period, $arg, $expected)
    {
        $this->assertSame($expected, $period->contains($arg));
    }

    public function provideContainsData()
    {
        return [
            'testContainsReturnsTrueWithADateTimeInterfaceObject' => [
                Period::createFromMonth(2014, 3),
                new DateTime('2014-03-12'),
                true,
            ],
            'testContainsReturnsTrueWithPeriodObject' => [
                Period::createFromSemester(2014, 1),
                Period::createFromQuarter(2014, 1),
                true,
            ],
            'testContainsReturnsFalseWithADateTimeInterfaceObject' => [
                Period::createFromMonth(2014, 3),
                new DateTime('2015-03-12'),
                false,
            ],
            'testContainsReturnsFalseWithADateTimeInterfaceObjectAfterPeriod' => [
                Period::createFromMonth(2014, 3),
                '2012-03-12',
                false,
            ],
            'testContainsReturnsFalseWithADateTimeInterfaceObjectBeforePeriod' => [
                Period::createFromMonth(2014, 3),
                '2014-04-01',
                false,
            ],
            'testContainsReturnsFalseWithAbutsPeriods' => [
                Period::createFromQuarter(2014, 1),
                Period::createFromSemester(2014, 1),
                false,
            ],
            'testContainsReturnsTrueWithPeriodObjectWhichShareTheSameEndDate' => [
                Period::createFromYear(2015),
                Period::createFromMonth(2015, 12),
                true,
            ],
            'testContainsReturnsTrueWithAZeroDurationObject' => [
                new Period('2012-03-12', '2012-03-12'),
                '2012-03-12',
                true,
            ],
        ];
    }

    /**
     * @dataProvider provideCompareDurationData
     * @param Period $period1
     * @param Period $period2
     * @param mixed  $method
     * @param mixed  $expected
     */
    public function testCompareDuration(Period $period1, Period $period2, $method, $expected)
    {
        $this->assertSame($expected, $period1->$method($period2));
    }

    public function provideCompareDurationData()
    {
        return [
            'testDurationLessThan' => [
                Period::createFromDuration('2012-01-01', '1 WEEK'),
                Period::createFromDuration('2013-01-01', '1 MONTH'),
                'durationLessThan',
                true,
            ],
            'testDurationGreaterThanReturnsTrue' => [
                Period::createFromDuration('2012-01-01', '1 MONTH'),
                Period::createFromDuration('2012-01-01', '1 WEEK'),
                'durationGreaterThan',
                true,
            ],
            'testSameDurationAsReturnsTrueWithMicroseconds' => [
                new Period('2012-01-01 00:00:00', '2012-01-03 00:00:00'),
                new Period('2012-02-02 00:00:00', '2012-02-04 00:00:00'),
                'sameDurationAs',
                true,
            ],
            'testSameValueAsReturnsTrue' => [
                Period::createFromDuration('2012-01-01', '1 MONTH'),
                Period::createFromMonth(2012, 1),
                'sameValueAs',
                true,
            ],
            'testSameValueAsReturnsFalse' => [
                Period::createFromDuration('2012-01-01', '1 MONTH'),
                Period::createFromDuration('2012-01-01', '1 WEEK'),
                'sameValueAs',
                false,
            ],
            'testSameValueAsReturnsFalseArgumentOrderIndependent' => [
                Period::createFromDurationBeforeEnd('2012-01-01', '1 WEEK'),
                Period::createFromDurationBeforeEnd('2012-01-01', '1 MONTH'),
                'sameValueAs',
                false,
            ],
        ];
    }

    public function testStartingOn()
    {
        $expected  = new DateTime('2012-03-02');
        $period = Period::createFromWeek(2014, 3);
        $newPeriod = $period->startingOn($expected);
        $this->assertTrue($newPeriod->getStartDate() == $expected);
        $this->assertEquals($period->getStartDate(), new DateTimeImmutable('2014-01-13'));
    }

    public function testStartingOnFailedWithWrongStartDate()
    {
        $this->expectException(Exception::class);
        $period = Period::createFromWeek(2014, 3);
        $period->startingOn(new DateTime('2015-03-02'));
    }

    public function testEndingOn()
    {
        $expected  = new DateTime('2015-03-02');
        $period = Period::createFromWeek(2014, 3);
        $newPeriod = $period->endingOn($expected);
        $this->assertTrue($newPeriod->getEndDate() == $expected);
        $this->assertEquals($period->getEndDate(), new DateTimeImmutable('2014-01-20'));
    }

    public function testEndingOnFailedWithWrongEndDate()
    {
        $this->expectException(Exception::class);
        $period = Period::createFromWeek(2014, 3);
        $period->endingOn(new DateTime('2012-03-02'));
    }

    public function testWithDuration()
    {
        $expected = Period::createFromMonth(2014, 3);
        $period = Period::createFromDuration('2014-03-01', '2 Weeks');
        $this->assertEquals($expected, $period->withDuration('1 MONTH'));
    }

    public function testWithDurationThrowsException()
    {
        $this->expectException(Exception::class);
        $period = Period::createFromDuration('2014-03-01', '2 Weeks');
        $interval = new DateInterval('P1D');
        $interval->invert = 1;
        $period->withDuration($interval);
    }


    public function testWithDurationBeforeEnd()
    {
        $expected = Period::createFromMonth(2014, 2);
        $period = Period::createFromDurationBeforeEnd('2014-03-01', '2 Weeks');
        $this->assertEquals($expected, $period->withDurationBeforeEnd('1 MONTH'));
    }

    public function testWithDurationBeforeEndThrowsException()
    {
        $this->expectException(Exception::class);
        $period = Period::createFromDurationBeforeEnd('2014-03-01', '2 Weeks');
        $interval = new DateInterval('P1D');
        $interval->invert = 1;
        $period->withDurationBeforeEnd($interval);
    }

    public function testMerge()
    {
        $period = Period::createFromMonth(2014, 3);
        $altPeriod = Period::createFromMonth(2014, 4);
        $expected = Period::createFromDuration('2014-03-01', '2 MONTHS');
        $this->assertEquals($expected, $period->merge($altPeriod));
        $this->assertEquals($expected, $altPeriod->merge($period));
        $this->assertEquals($expected, $expected->merge($period, $altPeriod));
    }

    public function testAdd()
    {
        $orig = Period::createFromDuration('2012-01-01', '1 MONTH');
        $period = $orig->moveEndDate('1 MONTH');
        $this->assertTrue($period->durationGreaterThan($orig));
        $this->assertEquals($orig->getStartDate(), $period->getStartDate());
    }

    public function testAddThrowsException()
    {
        $this->expectException(Exception::class);
        Period::createFromDuration('2012-01-01', '1 MONTH')->moveEndDate('-3 MONTHS');
    }

    public function testMoveStartDateBackward()
    {
        $orig = Period::createFromMonth(2012, 1);
        $period = $orig->moveStartDate('-1 MONTH');
        $this->assertTrue($period->durationGreaterThan($orig));
        $this->assertEquals($orig->getEndDate(), $period->getEndDate());
        $this->assertNotEquals($orig->getStartDate(), $period->getStartDate());
    }

    public function testMoveStartDateForward()
    {
        $orig = Period::createFromMonth(2012, 1);
        $period = $orig->moveStartDate('2 WEEKS');
        $this->assertTrue($period->durationLessThan($orig));
        $this->assertEquals($orig->getEndDate(), $period->getEndDate());
        $this->assertNotEquals($orig->getStartDate(), $period->getStartDate());
    }

    public function testMoveStartDateThrowsException()
    {
        $this->expectException(Exception::class);
        Period::createFromDuration('2012-01-01', '1 MONTH')->moveStartDate('3 MONTHS');
    }

    public function testSub()
    {
        $orig = Period::createFromDuration('2012-01-01', '1 MONTH');
        $period = $orig->moveEndDate('-1 WEEK');
        $this->assertTrue($period->durationLessThan($orig));
    }

    public function testSubThrowsException()
    {
        $this->expectException(Exception::class);
        Period::createFromDuration('2012-01-01', '1 MONTH')->moveEndDate('-3 MONTHS');
    }

    public function testExpand()
    {
        $period = Period::createFromDay('2012-02-02')->expand(new DateInterval('P1D'));
        $this->assertEquals(new DateTimeImmutable('2012-02-01'), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2012-02-04'), $period->getEndDate());
    }

    public function testShrink()
    {
        $dateInterval = new DateInterval('PT12H');
        $dateInterval->invert = 1;
        $period = Period::createFromDay('2012-02-02')->expand($dateInterval);
        $this->assertEquals(new DateTimeImmutable('2012-02-02 12:00:00'), $period->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2012-02-02 12:00:00'), $period->getEndDate());
    }

    public function testExpandThrowsException()
    {
        $this->expectException(Exception::class);
        $dateInterval = new DateInterval('P1D');
        $dateInterval->invert = 1;
        $period = Period::createFromDay('2012-02-02')->expand($dateInterval);
    }

    public function testDateIntervalDiff()
    {
        $orig = Period::createFromDuration('2012-01-01', '1 HOUR');
        $alt = Period::createFromDuration('2012-01-01', '2 HOUR');
        $this->assertInstanceOf(DateInterval::class, $orig->dateIntervalDiff($alt));
    }

    public function testTimeIntervalDiff()
    {
        $orig = Period::createFromDuration('2012-01-01', '1 HOUR');
        $alt = Period::createFromDuration('2012-01-01', '2 HOUR');
        $this->assertEquals(-3600, $orig->timestampIntervalDiff($alt));
    }

    public function testDateIntervalDiffPositionIrrelevant()
    {
        $orig = Period::createFromDuration('2012-01-01', '1 HOUR');
        $alt = Period::createFromDuration('2012-01-01', '2 HOUR');
        $fromOrig = $orig->dateIntervalDiff($alt);
        $fromOrig->invert = 1;
        $this->assertEquals($fromOrig, $alt->dateIntervalDiff($orig));
    }

    public function testIntersect()
    {
        $orig = Period::createFromDuration('2011-12-01', '5 MONTH');
        $alt = Period::createFromDuration('2012-01-01', '2 MONTH');

        $this->assertInstanceOf(Period::class, $orig->intersect($alt));
    }

    public function testIntersectThrowsExceptionWithNoOverlappingTimeRange()
    {
        $this->expectException(Exception::class);
        $orig = Period::createFromDuration('2013-01-01', '1 MONTH');
        $orig->intersect(Period::createFromDuration('2012-01-01', '2 MONTH'));
    }

    public function testGap()
    {
        $orig = Period::createFromDuration('2011-12-01', '2 MONTHS');
        $alt = Period::createFromDuration('2012-06-15', '3 MONTHS');
        $res = $orig->gap($alt);
        $this->assertInstanceOf(Period::class, $res);
        $this->assertEquals($orig->getEndDate(), $res->getStartDate());
        $this->assertEquals($alt->getStartDate(), $res->getEndDate());
        $this->assertTrue($res->sameValueAs($alt->gap($orig)));
    }

    public function testGapThrowsExceptionWithOverlapsPeriod()
    {
        $this->expectException(Exception::class);
        $orig = Period::createFromDuration('2011-12-01', '5 MONTH');
        $orig->gap(Period::createFromDuration('2012-01-01', '2 MONTH'));
    }

    public function testGapWithSameStartingPeriod()
    {
        $this->expectException(Exception::class);
        $orig = Period::createFromDuration('2012-12-01', '5 MONTH');
        $orig->gap(Period::createFromDuration('2012-12-01', '2 MONTH'));
    }

    public function testGapWithSameEndingPeriod()
    {
        $this->expectException(Exception::class);
        $orig = Period::createFromDurationBeforeEnd('2012-12-01', '5 MONTH');
        $orig->gap(Period::createFromDurationBeforeEnd('2012-12-01', '2 MONTH'));
    }

    public function testGapWithAdjacentPeriod()
    {
        $orig = Period::createFromDurationBeforeEnd('2012-12-01', '5 MONTH');
        $alt  = Period::createFromDuration($orig->getEndDate(), '1 MINUTE');
        $gap  = $orig->gap($alt);
        $this->assertInstanceOf(Period::class, $gap);
        $this->assertEquals(0, $gap->getTimestampInterval());
    }

    public function testDiffThrowsException()
    {
        $this->expectException(Exception::class);
        Period::createFromYear(2015)->diff(Period::createFromYear(2013));
    }

    public function testDiffWithEqualsPeriod()
    {
        $period = Period::createFromYear(2013);
        $alt = Period::createFromDuration('2013-01-01', '1 YEAR');
        $this->assertCount(0, $alt->diff($period));
    }

    public function testDiffWithPeriodSharingOneEndpoints()
    {
        $period = Period::createFromYear(2013);
        $alt = Period::createFromDuration('2013-01-01', '3 MONTHS');
        $res = $alt->diff($period);
        $this->assertCount(1, $res);
        $this->assertInstanceOf(Period::class, $res[0]);
        $this->assertEquals(new DateTimeImmutable('2013-04-01'), $res[0]->getStartDate());
        $this->assertEquals(new DateTimeImmutable('2014-01-01'), $res[0]->getEndDate());
    }

    public function testDiffWithOverlapsPeriod()
    {
        $period = Period::createFromDuration('2013-01-01 10:00:00', '3 HOURS');
        $alt = Period::createFromDuration('2013-01-01 11:00:00', '3 HOURS');
        $res = $alt->diff($period);
        $this->assertCount(2, $res);
        $this->assertEquals(3600, $res[1]->getTimestampInterval());
        $this->assertEquals(3600, $res[0]->getTimestampInterval());
    }

    public function testMove()
    {
        $period = new Period('2016-01-01 15:32:12', '2016-01-15 12:00:01');
        $moved = $period->move(new DateInterval('P1D'));
        $this->assertEquals(new Period('2016-01-02 15:32:12', '2016-01-16 12:00:01'), $moved);
    }

    public function testMoveSupportStringIntervals()
    {
        $period = new Period('2016-01-01 15:32:12', '2016-01-15 12:00:01');
        $advanced = $period->move('1 DAY');
        $this->assertEquals(new Period('2016-01-02 15:32:12', '2016-01-16 12:00:01'), $advanced);
    }

    public function testMoveWithInvertedInterval()
    {
        $period = new Period('2016-01-02 15:32:12', '2016-01-16 12:00:01');
        $lessOneDay = new DateInterval('P1D');
        $lessOneDay->invert = 1;
        $moved = $period->move($lessOneDay);
        $this->assertEquals(new Period('2016-01-01 15:32:12', '2016-01-15 12:00:01'), $moved);
    }

    public function testMoveWithInvertedStringInterval()
    {
        $period = new Period('2016-01-02 15:32:12', '2016-01-16 12:00:01');
        $moved = $period->move('- 1 day');
        $this->assertEquals(new Period('2016-01-01 15:32:12', '2016-01-15 12:00:01'), $moved);
    }
}