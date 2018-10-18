-- database schema IQB Itemdatenbank
-- for PostgreSQL

-- vo stands for VERA online

-- CREATE DATABASE not tested (dummy)
CREATE DATABASE votcdb
    WITH 
    OWNER = votc_admin
    ENCODING = 'UTF8'
    LC_COLLATE = 'de_DE.UTF-8'
    LC_CTYPE = 'de_DE.UTF-8'
    TABLESPACE = pg_default
    CONNECTION LIMIT = -1;

GRANT ALL ON DATABASE votcdb TO votc_admin;



CREATE TABLE public.users
(
    id serial,
    name character varying(50) NOT NULL,
    password character varying(100) NOT NULL,
    email character varying(100),
    is_superadmin boolean NOT NULL DEFAULT false,
    CONSTRAINT pk_users PRIMARY KEY (id)
);

CREATE TABLE public.workspaces
(
    id serial,
    name character varying(50) NOT NULL,
    CONSTRAINT pk_workspaces PRIMARY KEY (id)
);

CREATE TABLE public.workspace_users
(
    workspace_id integer NOT NULL,
    user_id integer NOT NULL,
    CONSTRAINT pk_workspace_users PRIMARY KEY (workspace_id, user_id),
    CONSTRAINT fk_workspace_users_user FOREIGN KEY (user_id)
        REFERENCES public.users (id) MATCH SIMPLE
        ON UPDATE NO ACTION
        ON DELETE CASCADE,
    CONSTRAINT fk_workspace_users_workspace FOREIGN KEY (workspace_id)
        REFERENCES public.workspaces (id) MATCH SIMPLE
        ON UPDATE NO ACTION
        ON DELETE CASCADE
);

CREATE TABLE public.sessions
(
    token character(30) COLLATE pg_catalog."default" NOT NULL,
    user_id integer NOT NULL,
    valid_until timestamp without time zone NOT NULL,
    CONSTRAINT pk_sessions PRIMARY KEY (token),
    CONSTRAINT fk_users_sessions FOREIGN KEY (user_id)
        REFERENCES public.users (id) MATCH SIMPLE
        ON UPDATE NO ACTION
        ON DELETE CASCADE
);

CREATE TABLE public.units
(
    id serial,
    workspace_id integer NOT NULL,
    lastchanged timestamp without time zone NOT NULL DEFAULT now(),
    key character varying(20) COLLATE pg_catalog."default" NOT NULL,
    label character varying(50) COLLATE pg_catalog."default",
    description text COLLATE pg_catalog."default",
    def text COLLATE pg_catalog."default",
    authoringtool_id character varying(50) COLLATE pg_catalog."default",
    player_id character varying(50) COLLATE pg_catalog."default",
    defref character varying(50) COLLATE pg_catalog."default",
    CONSTRAINT pk_units PRIMARY KEY (id),
    CONSTRAINT fk_units_workspace FOREIGN KEY (workspace_id)
        REFERENCES public.workspaces (id) MATCH SIMPLE
        ON UPDATE NO ACTION
        ON DELETE CASCADE
);