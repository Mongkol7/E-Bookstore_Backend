-- ====================================
-- E-bookStore Database Complete Schema
-- ====================================

-- Create authors table
CREATE TABLE authors (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    bio TEXT
);

-- Create categories table
CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL
);

-- Create books table (with image column)
CREATE TABLE books (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author_id BIGINT,
    category_id BIGINT,
    price DECIMAL(10,2) NOT NULL,
    stock INTEGER NOT NULL,
    description TEXT,
    published_date DATE,
    image TEXT,
    CONSTRAINT fk_books_author
        FOREIGN KEY (author_id) REFERENCES authors(id),
    CONSTRAINT fk_books_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
);

-- Create customers table
CREATE TABLE customers (
    id SERIAL PRIMARY KEY,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone TEXT,
    address TEXT,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMPTZ
);

-- ====================================
-- Stored Procedures
-- ====================================

-- Store Procedure (INSERT Book)
CREATE PROCEDURE createBook(
    title VARCHAR(255), 
    author_id INT, 
    category_id INT, 
    price DECIMAL(10,2), 
    stock INT, 
    description TEXT, 
    published_date DATE,
    image TEXT
)
LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO books (title, author_id, category_id, price, stock, description, published_date, image)
    VALUES (title, author_id, category_id, price, stock, description, published_date, image);
END;
$$;

-- Store Procedure (UPDATE Book)
CREATE PROCEDURE updateBook(
    book_id INT,
    new_title VARCHAR(255),
    new_author_id INT,
    new_category_id INT,
    new_price DECIMAL(10,2),
    new_stock INT,
    new_description TEXT,
    new_published_date DATE,
    new_image TEXT
)
LANGUAGE plpgsql
AS $$
BEGIN
    UPDATE books
    SET
        title = new_title,
        author_id = new_author_id,
        category_id = new_category_id,
        price = new_price,
        stock = new_stock,
        description = new_description,
        published_date = new_published_date,
        image = new_image
    WHERE
        id = book_id;
END;
$$;

-- Store Procedure (SELECT ALL Books) - Note: This is corrected from the original prompt
CREATE PROCEDURE selectAllBook()
LANGUAGE plpgsql
AS $$
BEGIN
    -- Note: A SELECT statement inside a procedure like this does not return results to the client in psql.
    -- A FUNCTION or a VIEW would be a more standard way to return a result set.
    -- The query has been corrected to use the right table and column names.
    SELECT * FROM books b JOIN authors a ON b.author_id = a.id;
END;
$$;

select * from books;

-- ====================================
-- Sample Data for E-bookStore Database
-- ====================================

-- Insert sample authors
INSERT INTO authors (name, bio) VALUES
('J.K. Rowling', 'British author best known for the Harry Potter fantasy series'),
('George Orwell', 'English novelist and essayist, journalist and critic'),
('Jane Austen', 'English novelist known for her romantic fiction'),
('Stephen King', 'American author of horror, supernatural fiction, suspense'),
('Agatha Christie', 'English writer known for detective novels');

-- Insert sample categories
INSERT INTO categories (name) VALUES
('Fantasy'),
('Science Fiction'),
('Classic Literature'),
('Horror'),
('Mystery'),
('Romance'),
('Thriller');

-- ====================================
-- Sample Books using createBook procedure
-- ====================================

-- Fantasy Books
CALL createBook(
    'Harry Potter and the Sorcerer''s Stone',
    1,
    1,
    19.99,
    150,
    'The first book in the Harry Potter series. Follow Harry as he discovers he is a wizard and attends Hogwarts School of Witchcraft and Wizardry.',
    '1997-06-26',
    'images/books/harry-potter-1.jpg'
);

CALL createBook(
    'Harry Potter and the Chamber of Secrets',
    1,
    1,
    19.99,
    120,
    'Harry returns to Hogwarts for his second year, where a mysterious monster is petrifying students.',
    '1998-07-02',
    'images/books/harry-potter-2.jpg'
);

-- Science Fiction / Classic
CALL createBook(
    '1984',
    2,
    2,
    15.99,
    200,
    'A dystopian social science fiction novel set in Airstrip One, a province of the superstate Oceania.',
    '1949-06-08',
    'images/books/1984.jpg'
);

CALL createBook(
    'Animal Farm',
    2,
    2,
    12.99,
    180,
    'A satirical allegorical novella about a group of farm animals who rebel against their human farmer.',
    '1945-08-17',
    'images/books/animal-farm.jpg'
);

-- Classic Literature
CALL createBook(
    'Pride and Prejudice',
    3,
    3,
    14.99,
    160,
    'A romantic novel of manners following the character development of Elizabeth Bennet.',
    '1813-01-28',
    'images/books/pride-prejudice.jpg'
);

CALL createBook(
    'Sense and Sensibility',
    3,
    3,
    13.99,
    140,
    'The story of the Dashwood sisters, Elinor and Marianne, who experience love and heartbreak.',
    '1811-10-30',
    'images/books/sense-sensibility.jpg'
);

-- Horror Books
CALL createBook(
    'The Shining',
    4,
    4,
    16.99,
    130,
    'A family heads to an isolated hotel for the winter where a sinister presence influences the father into violence.',
    '1977-01-28',
    'images/books/the-shining.jpg'
);

CALL createBook(
    'IT',
    4,
    4,
    18.99,
    110,
    'Seven children are terrorized by an entity that exploits the fears of its victims to disguise itself.',
    '1986-09-15',
    'images/books/it.jpg'
);

CALL createBook(
    'Carrie',
    4,
    4,
    14.99,
    95,
    'A shy high school student discovers she has telekinetic powers after being bullied.',
    '1974-04-05',
    'images/books/carrie.jpg'
);

-- Mystery Books
CALL createBook(
    'Murder on the Orient Express',
    5,
    5,
    15.99,
    170,
    'Detective Hercule Poirot investigates a murder on the famous European train.',
    '1934-01-01',
    'images/books/orient-express.jpg'
);

CALL createBook(
    'And Then There Were None',
    5,
    5,
    14.99,
    155,
    'Ten strangers are invited to an island where they are accused of murder and killed one by one.',
    '1939-11-06',
    'images/books/and-then-there-were-none.jpg'
);

CALL createBook(
    'The ABC Murders',
    5,
    5,
    13.99,
    145,
    'A serial killer challenges Hercule Poirot by committing murders in alphabetical order.',
    '1936-01-06',
    'images/books/abc-murders.jpg'
);

-- Additional Fantasy Books
CALL createBook(
    'Harry Potter and the Prisoner of Azkaban',
    1,
    1,
    19.99,
    135,
    'Harry learns more about his past as a dangerous prisoner escapes from Azkaban.',
    '1999-07-08',
    'images/books/harry-potter-3.jpg'
);

CALL createBook(
    'Harry Potter and the Goblet of Fire',
    1,
    1,
    22.99,
    125,
    'Harry competes in a dangerous tournament between three schools of magic.',
    '2000-07-08',
    'images/books/harry-potter-4.jpg'
);

-- Additional Stephen King Books
CALL createBook(
    'Pet Sematary',
    4,
    4,
    16.99,
    88,
    'A family discovers a mysterious burial ground in the woods behind their home.',
    '1983-11-14',
    'images/books/pet-sematary.jpg'
);


select * from books;
select * from authors;

-- Create admins table
CREATE TABLE admins (
    id SERIAL PRIMARY KEY,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    email TEXT UNIQUE NOT NULL,
    phone TEXT,
    address TEXT,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
password VARCHAR(255) NOT NULL DEFAULT '123',
last_login TIMESTAMPTZ
);