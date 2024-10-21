<?php


class ApiController {

    const OBJECT = 1;
    const JSON = 2;

    private $connection;

    public function __construct() {
        $this->connection = DatabaseController::connect();
    }

    public function getLinks($mode = self::OBJECT) {

        try  {
       
            $sql = "SELECT * 
                    FROM User
                    WHERE 1";
        
            $statement = $this->connection->prepare($sql);
            $statement->setFetchMode(PDO::FETCH_OBJ);
            $statement->execute();

            $result = $statement->fetchAll();

            if (mode == self::OBJECT) {
                return $result;
            } else if (mode == self::JSON) {
                return json_encode($result);
            }

          } catch(PDOException $error) {
              echo $sql . "<br>" . $error->getMessage();
          }
    }
}