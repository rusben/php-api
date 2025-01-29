<?php


class ApiController {

    const OBJECT = 1;
    const JSON = 2;

    private $connection;

    public function __construct() {
        $this->connection = DatabaseController::connect();
    }

    public static function getUsers($mode = self::OBJECT) {

        try  {
       
            $sql = "SELECT * 
                    FROM User
                    WHERE 1";
        
            
            $statement = (new self)->connection->prepare($sql);
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $statement->execute();

            $result = $statement->fetchAll();

            if ($mode == self::OBJECT) {
                return $result;
            } else if ($mode == self::JSON) {
                return json_encode($result, JSON_PRETTY_PRINT);
            }

          } catch(PDOException $error) {
              echo $sql . "<br>" . $error->getMessage();
          }
    }

    // Add a new User to the system
    public static function addUser($mode = self::OBJECT)
    {
        // Get the JSON
        $input = file_get_contents('php://input');
        // Decode the JSON data into a PHP associative array
        $data = json_decode($input, true);

        // Verify JSON is OK
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['status' => 'error', 'message' => 'JSON format not valid']);
            return;
        }

        // Check errors
        if ($data !== null) {
            
            if (self::verifyRequieredFields($data)) {
                try {
                    // SQL NEW USER
                    $sql = "INSERT INTO User 
                                (name, surname, email, dni, phone, born)
                            VALUES (:name, :surname, :email, :dni, :phone, :born)";
        
                    // Prepare the query
                    $statement = (new self)->connection->prepare($sql);
        
                    // Asssign values
                    $statement->bindValue(':name', $data['name']);
                    $statement->bindValue(':surname', $data['surname']);
                    $statement->bindValue(':email', $data['email']);
                    $statement->bindValue(':dni', $data['dni']);
                    $statement->bindValue(':phone', $data['phone']);
                    $statement->bindValue(':born', $data['born']);
        
                    // Execute
                    if ($statement->execute()) {
                        echo json_encode(["status" => "success", "message" => "User created successfully."]);
                    } else {
                        echo json_encode(["status" => "error", "message" => "Error while creating the user."]);
                    }
        
                } catch (PDOException $error) {
                    // Unexpected error
                    echo json_encode(["status" => "error", "message" => "Database error: ".$error->getMessage()]);
                }
            }

        } else {
            // JSON decoding failed
            http_response_code(400); // Bad Request
            echo json_encode(["status" => "error", "message" => "Invalid JSON data"]);
        }        
    }

    public static function verifyRequieredFields($data) {

        // Define required fields
        $requiredFields = ["name", "surname", "email", "dni", "phone", "born"];
        $missingFields = [];

        // Verify all fields are in $data
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                // Agregar el campo faltante al array /
                $missingFields[] = $field;
            }
        }

        // Check for missing fields
        if (!empty($missingFields)) {
            echo json_encode(["status" => "error", "message" => "Missing required fields: " . implode(', ', $missingFields)]);
            return false;
        } else {
            return true;
        }

    }

    public static function getUser($id, $mode = self::OBJECT)
    {
        try {

            $sql = "SELECT * 
                    FROM User
                    WHERE id = :id";


            $statement = (new self)->connection->prepare($sql);
            $statement->bindValue(":id", $id);
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $statement->execute();

            $result = $statement->fetch();

            // Verify user exists
            if ($result) {
                if ($mode == self::OBJECT) {
                    return $result;
                } else if ($mode == self::JSON) {
                    return json_encode($result, JSON_PRETTY_PRINT);
                }
            } else {
                return json_encode(['status' => 'error', 'message' => 'User not found'], JSON_PRETTY_PRINT);
            }

        } catch (PDOException $error) {
            echo json_encode(["status" => "error", "message" => $error->getMessage()]);
        }
    }

    public static function updateUser($id, $mode = self::OBJECT)
    {
        // Get the JSON
        $input = file_get_contents('php://input');
        // Decode the JSON data into a PHP associative array
        $data = json_decode($input, true);

        // Verify JSON is OK
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['status' => 'error', 'message' => 'JSON format not valid']);
            return;
        }

        // Define the valid fields
        $requiredFields = [
            "name" => "name",
            "surname" => "surname",
            "email" => "email",
            "dni" => "dni",
            "phone" => "phone",
            "born" => "born"
        ];
        $presentFields = [];

        // Verify which fields are in the request
        foreach ($data as $key => $value) {
            if (isset($requiredFields[$key])) {
                // Add the fields presents in the request
                $presentFields[] = $key;
            } else {
                // There is a invalid field in the request, stop processing
                echo json_encode(['status' => 'error', 'message' => 'There are invalid fields in request']);
                return;
            }
        }

        // The request is empty or not required fields in the request
        if (empty($presentFields)) {
            echo json_encode(['status' => 'error', 'message' => 'The request is empty or not valid fields in the request']);
            return;
        }

        try {
            // Define the query
            $sql = "UPDATE User SET ";
            $sql .= implode(", ", array_map(fn($presentField) => "$presentField = :$presentField", $presentFields));
            $sql .= " WHERE id = :id;";

            
            // Prepare the query
            $statement = (new self)->connection->prepare($sql);

            // Assign the values
            $statement->bindValue(':id', $id);
            foreach ($presentFields as $presentField) {
                $statement->bindValue(":".$presentField, $data[$presentField]);
            }

            // Execute the query and verify the result
            if ($statement->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'User updated successfuly.']);
                return;
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error updating the user.']);
                return;
            }

        } catch (PDOException $error) {
            echo json_encode(['status' => 'error', 'message' => $error->getMessage()]);
            return;
        }
    }

    public static function deleteUser($id)
    {
        try {
            // Define the query
            $sql = "DELETE FROM User WHERE id = :id";

            // Prepare the connection
            $statement = (new self)->connection->prepare($sql);
            // Asign the values
            $statement->bindValue(':id', $id);

            // Check user exist
            if (userExist($id)) {
                // Execute the query
                if ($statement->execute()) {
                    echo json_encode(['status' => 'success', 'message' => 'User deleted successfully']);
                    return;
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Error deleting the user']);
                    return;
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error, no user found with this id']);
                return;
            }

        } catch (PDOException $error) {
            echo json_encode(['status' => 'error', 'message' => $error->getMessage()]);
            return;
        }
    }

    public static function userExist($id) {
        try {

            $sql = "SELECT * 
                    FROM User
                    WHERE id = :id";


            $statement = (new self)->connection->prepare($sql);
            $statement->bindValue(":id", $id);
            $statement->setFetchMode(PDO::FETCH_ASSOC);
            $statement->execute();

            $result = $statement->fetch();

            // Verify user exists
            if ($result) {
                return true;
            } else {
                return false;
            }

        } catch (PDOException $error) {
            echo json_encode(["status" => "error", "message" => $error->getMessage()]);
            return false;
        }
    }

}