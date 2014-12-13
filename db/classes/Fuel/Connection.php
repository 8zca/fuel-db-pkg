<?php
/**
 * PDO database connection.
 *
 * @package    Fuel/Database
 * @category   Drivers
 * @author     Kohana Team
 * @copyright  (c) 2008-2009 Kohana Team
 * @license    http://kohanaphp.com/license
 */

namespace Db\Fuel;

class Database_PDO_Connection extends \Fuel\Core\Database_PDO_Connection
{
    /**
     * Query the database
     *
     * @param integer $type
     * @param string  $sql
     * @param mixed   $as_object
     *
     * @return mixed
     *
     * @throws \Database_Exception
     */
    public function query($type, $sql, $as_object)
    {
        // Make sure the database is connected
        $this->_connection or $this->connect();

        if ( ! empty($this->_config['profiling']))
        {
            // Get the paths defined in config
            $paths = \Config::get('profiling_paths');

            // Storage for the trace information
            $stacktrace = array();

            // Get the execution trace of this query
            $include = false;
            foreach (debug_backtrace() as $index => $page)
            {
                // Skip first entry and entries without a filename
                if ($index > 0 and empty($page['file']) === false)
                {
                    // Checks to see what paths you want backtrace
                    foreach($paths as $index => $path)
                    {
                        if (strpos($page['file'], $path) !== false)
                        {
                            $include = true;
                            break;
                        }
                    }

                    // Only log if no paths we defined, or we have a path match
                    if ($include or empty($paths))
                    {
                        $stacktrace[] = array('file' => Fuel::clean_path($page['file']), 'line' => $page['line']);
                    }
                }
            }

            $benchmark = \Profiler::start($this->_instance, $sql, $stacktrace);
        }

        // run the query. if the connection is lost, try 3 times to reconnect
        $attempts = 3;

        do
        {
            try
            {
                // try to run the query
                $result = $this->_connection->query($sql);
                break;
            }
            catch (\Exception $e)
            {
                // if failed and we have attempts left
                if ($attempts > 0)
                {
                    // try reconnecting if it was a MySQL disconnected error
                    if (strpos($e->getMessage(), '2006 MySQL') !== false)
                    {
                        $this->disconnect();
                        $this->connect();
                    }
                    else
                    {
                        // other database error, cleanup the profiler
                        isset($benchmark) and  \Profiler::delete($benchmark);

                        // and convert the exception in a database exception
                        if ( ! is_numeric($error_code = $e->getCode()))
                        {
                            if ($this->_connection)
                            {
                                $error_code = $this->_connection->errorinfo();
                                $error_code = $error_code[1];
                            }
                            else
                            {
                                $error_code = 0;
                            }
                        }

                        throw new \Database_Exception($e->getMessage().' with query: "'.$sql.'"', $error_code, $e);
                    }
                }

                // no more attempts left, bail out
                else
                {
                    // and convert the exception in a database exception
                    if ( ! is_numeric($error_code = $e->getCode()))
                    {
                        if ($this->_connection)
                        {
                            $error_code = $this->_connection->errorinfo();
                            $error_code = $error_code[1];
                        }
                        else
                        {
                            $error_code = 0;
                        }
                    }
                    throw new \Database_Exception($e->getMessage().' with query: "'.$sql.'"', $error_code, $e);
                }
            }
        }
        while ($attempts-- > 0);

        if (isset($benchmark))
        {
            \Profiler::stop($benchmark);
        }

        // Set the last query
        $this->last_query = $sql;

        if ($type === \DB::SELECT)
        {
            // Convert the result into an array, as PDOStatement::rowCount is not reliable
            if ($as_object === false)
            {
                $result = $result->fetchAll(\PDO::FETCH_ASSOC);
            }
            elseif (is_string($as_object))
            {
                $result = $result->fetchAll(\PDO::FETCH_CLASS, $as_object);
            }
            else
            {
                $result = $result->fetchAll(\PDO::FETCH_CLASS, 'stdClass');
            }


            // Return an iterator of results
            return new \Database_Result_Cached($result, $sql, $as_object);
        }
        elseif ($type === \DB::INSERT)
        {
            \Log::info('insert end');
            // Return a list of insert id and rows created
            return array(
                -1,
                $result->rowCount(),
            );
        }
        else
        {
            // Return the number of rows affected
            return $result->errorCode() === '00000' ? $result->rowCount() : -1;
        }
    }

}
