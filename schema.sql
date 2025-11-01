--
-- PostgreSQL database dump
--

-- Dumped from database version 16.9
-- Dumped by pg_dump version 16.9

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

ALTER TABLE IF EXISTS ONLY public.wallets DROP CONSTRAINT IF EXISTS wallets_user_id_fkey;
ALTER TABLE IF EXISTS ONLY public.wallet_transactions DROP CONSTRAINT IF EXISTS wallet_transactions_wallet_id_fkey;
ALTER TABLE IF EXISTS ONLY public.wallet_transactions DROP CONSTRAINT IF EXISTS wallet_transactions_user_id_fkey;
ALTER TABLE IF EXISTS ONLY public.settings DROP CONSTRAINT IF EXISTS settings_updated_by_fkey;
ALTER TABLE IF EXISTS ONLY public.giveaway_entries DROP CONSTRAINT IF EXISTS giveaway_entries_user_id_fkey;
ALTER TABLE IF EXISTS ONLY public.giveaway_entries DROP CONSTRAINT IF EXISTS giveaway_entries_broadcast_id_fkey;
ALTER TABLE IF EXISTS ONLY public.deposit_payments DROP CONSTRAINT IF EXISTS fk_deposit_payments_user;
ALTER TABLE IF EXISTS ONLY public.deposits DROP CONSTRAINT IF EXISTS deposits_wallet_transaction_id_fkey;
ALTER TABLE IF EXISTS ONLY public.deposits DROP CONSTRAINT IF EXISTS deposits_wallet_id_fkey;
ALTER TABLE IF EXISTS ONLY public.deposits DROP CONSTRAINT IF EXISTS deposits_user_id_fkey;
ALTER TABLE IF EXISTS ONLY public.deposits DROP CONSTRAINT IF EXISTS deposits_rejected_by_fkey;
ALTER TABLE IF EXISTS ONLY public.deposits DROP CONSTRAINT IF EXISTS deposits_approved_by_fkey;
ALTER TABLE IF EXISTS ONLY public.cards DROP CONSTRAINT IF EXISTS cards_user_id_fkey;
ALTER TABLE IF EXISTS ONLY public.cards DROP CONSTRAINT IF EXISTS cards_frozen_by_fkey;
ALTER TABLE IF EXISTS ONLY public.cards DROP CONSTRAINT IF EXISTS cards_creation_wallet_transaction_id_fkey;
ALTER TABLE IF EXISTS ONLY public.card_transactions DROP CONSTRAINT IF EXISTS card_transactions_wallet_transaction_id_fkey;
ALTER TABLE IF EXISTS ONLY public.card_transactions DROP CONSTRAINT IF EXISTS card_transactions_user_id_fkey;
ALTER TABLE IF EXISTS ONLY public.card_transactions DROP CONSTRAINT IF EXISTS card_transactions_card_id_fkey;
ALTER TABLE IF EXISTS ONLY public.broadcasts DROP CONSTRAINT IF EXISTS broadcasts_created_by_fkey;
ALTER TABLE IF EXISTS ONLY public.broadcast_logs DROP CONSTRAINT IF EXISTS broadcast_logs_broadcast_id_fkey;
ALTER TABLE IF EXISTS ONLY public.admin_actions DROP CONSTRAINT IF EXISTS admin_actions_admin_id_fkey;
DROP TRIGGER IF EXISTS update_wallets_updated_at ON public.wallets;
DROP TRIGGER IF EXISTS update_wallet_transactions_updated_at ON public.wallet_transactions;
DROP TRIGGER IF EXISTS update_users_updated_at ON public.users;
DROP TRIGGER IF EXISTS update_deposits_updated_at ON public.deposits;
DROP TRIGGER IF EXISTS update_cards_updated_at ON public.cards;
DROP TRIGGER IF EXISTS update_admin_users_updated_at ON public.admin_users;
DROP TRIGGER IF EXISTS trigger_deposit_payments_updated_at ON public.deposit_payments;
DROP INDEX IF EXISTS public.idx_wallets_user_id;
DROP INDEX IF EXISTS public.idx_wallet_transactions_wallet_id;
DROP INDEX IF EXISTS public.idx_wallet_transactions_user_id;
DROP INDEX IF EXISTS public.idx_wallet_transactions_type;
DROP INDEX IF EXISTS public.idx_wallet_transactions_status;
DROP INDEX IF EXISTS public.idx_users_telegram_id;
DROP INDEX IF EXISTS public.idx_users_kyc_status;
DROP INDEX IF EXISTS public.idx_users_email;
DROP INDEX IF EXISTS public.idx_user_registrations_telegram_id;
DROP INDEX IF EXISTS public.idx_user_registrations_strowallet_id;
DROP INDEX IF EXISTS public.idx_user_registrations_state;
DROP INDEX IF EXISTS public.idx_user_registrations_kyc_status;
DROP INDEX IF EXISTS public.idx_user_registrations_is_registered;
DROP INDEX IF EXISTS public.idx_giveaway_entries_user;
DROP INDEX IF EXISTS public.idx_giveaway_entries_broadcast;
DROP INDEX IF EXISTS public.idx_deposits_wallet_id;
DROP INDEX IF EXISTS public.idx_deposits_user_id;
DROP INDEX IF EXISTS public.idx_deposits_status;
DROP INDEX IF EXISTS public.idx_deposits_created_at;
DROP INDEX IF EXISTS public.idx_deposit_payments_validation_status;
DROP INDEX IF EXISTS public.idx_deposit_payments_transaction_number;
DROP INDEX IF EXISTS public.idx_deposit_payments_telegram_id;
DROP INDEX IF EXISTS public.idx_deposit_payments_status;
DROP INDEX IF EXISTS public.idx_deposit_payments_created_at;
DROP INDEX IF EXISTS public.idx_cards_user_id;
DROP INDEX IF EXISTS public.idx_cards_strow_card_id;
DROP INDEX IF EXISTS public.idx_cards_status;
DROP INDEX IF EXISTS public.idx_card_transactions_user_id;
DROP INDEX IF EXISTS public.idx_card_transactions_type;
DROP INDEX IF EXISTS public.idx_card_transactions_date;
DROP INDEX IF EXISTS public.idx_card_transactions_card_id;
DROP INDEX IF EXISTS public.idx_broadcasts_status;
DROP INDEX IF EXISTS public.idx_broadcasts_scheduled;
DROP INDEX IF EXISTS public.idx_broadcast_logs_broadcast;
DROP INDEX IF EXISTS public.idx_admin_users_username;
DROP INDEX IF EXISTS public.idx_admin_users_email;
DROP INDEX IF EXISTS public.idx_admin_actions_type;
DROP INDEX IF EXISTS public.idx_admin_actions_created_at;
DROP INDEX IF EXISTS public.idx_admin_actions_admin_id;
ALTER TABLE IF EXISTS ONLY public.wallets DROP CONSTRAINT IF EXISTS wallets_user_id_key;
ALTER TABLE IF EXISTS ONLY public.wallets DROP CONSTRAINT IF EXISTS wallets_pkey;
ALTER TABLE IF EXISTS ONLY public.wallet_transactions DROP CONSTRAINT IF EXISTS wallet_transactions_pkey;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_telegram_id_key;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_pkey;
ALTER TABLE IF EXISTS ONLY public.users DROP CONSTRAINT IF EXISTS users_email_key;
ALTER TABLE IF EXISTS ONLY public.user_registrations DROP CONSTRAINT IF EXISTS user_registrations_telegram_user_id_key;
ALTER TABLE IF EXISTS ONLY public.user_registrations DROP CONSTRAINT IF EXISTS user_registrations_pkey;
ALTER TABLE IF EXISTS ONLY public.settings DROP CONSTRAINT IF EXISTS settings_pkey;
ALTER TABLE IF EXISTS ONLY public.giveaway_entries DROP CONSTRAINT IF EXISTS giveaway_entries_pkey;
ALTER TABLE IF EXISTS ONLY public.giveaway_entries DROP CONSTRAINT IF EXISTS giveaway_entries_broadcast_id_telegram_user_id_key;
ALTER TABLE IF EXISTS ONLY public.deposits DROP CONSTRAINT IF EXISTS deposits_pkey;
ALTER TABLE IF EXISTS ONLY public.deposit_payments DROP CONSTRAINT IF EXISTS deposit_payments_pkey;
ALTER TABLE IF EXISTS ONLY public.cards DROP CONSTRAINT IF EXISTS cards_strow_card_id_key;
ALTER TABLE IF EXISTS ONLY public.cards DROP CONSTRAINT IF EXISTS cards_pkey;
ALTER TABLE IF EXISTS ONLY public.card_transactions DROP CONSTRAINT IF EXISTS card_transactions_pkey;
ALTER TABLE IF EXISTS ONLY public.broadcasts DROP CONSTRAINT IF EXISTS broadcasts_pkey;
ALTER TABLE IF EXISTS ONLY public.broadcast_logs DROP CONSTRAINT IF EXISTS broadcast_logs_pkey;
ALTER TABLE IF EXISTS ONLY public.admin_users DROP CONSTRAINT IF EXISTS admin_users_username_key;
ALTER TABLE IF EXISTS ONLY public.admin_users DROP CONSTRAINT IF EXISTS admin_users_pkey;
ALTER TABLE IF EXISTS ONLY public.admin_users DROP CONSTRAINT IF EXISTS admin_users_email_key;
ALTER TABLE IF EXISTS ONLY public.admin_actions DROP CONSTRAINT IF EXISTS admin_actions_pkey;
ALTER TABLE IF EXISTS public.wallets ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.wallet_transactions ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.users ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.user_registrations ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.giveaway_entries ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.deposits ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.deposit_payments ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.cards ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.card_transactions ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.broadcasts ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.broadcast_logs ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.admin_users ALTER COLUMN id DROP DEFAULT;
ALTER TABLE IF EXISTS public.admin_actions ALTER COLUMN id DROP DEFAULT;
DROP SEQUENCE IF EXISTS public.wallets_id_seq;
DROP TABLE IF EXISTS public.wallets;
DROP SEQUENCE IF EXISTS public.wallet_transactions_id_seq;
DROP TABLE IF EXISTS public.wallet_transactions;
DROP SEQUENCE IF EXISTS public.users_id_seq;
DROP TABLE IF EXISTS public.users;
DROP SEQUENCE IF EXISTS public.user_registrations_id_seq;
DROP TABLE IF EXISTS public.user_registrations;
DROP TABLE IF EXISTS public.settings;
DROP SEQUENCE IF EXISTS public.giveaway_entries_id_seq;
DROP TABLE IF EXISTS public.giveaway_entries;
DROP SEQUENCE IF EXISTS public.deposits_id_seq;
DROP TABLE IF EXISTS public.deposits;
DROP SEQUENCE IF EXISTS public.deposit_payments_id_seq;
DROP TABLE IF EXISTS public.deposit_payments;
DROP SEQUENCE IF EXISTS public.cards_id_seq;
DROP TABLE IF EXISTS public.cards;
DROP SEQUENCE IF EXISTS public.card_transactions_id_seq;
DROP TABLE IF EXISTS public.card_transactions;
DROP SEQUENCE IF EXISTS public.broadcasts_id_seq;
DROP TABLE IF EXISTS public.broadcasts;
DROP SEQUENCE IF EXISTS public.broadcast_logs_id_seq;
DROP TABLE IF EXISTS public.broadcast_logs;
DROP SEQUENCE IF EXISTS public.admin_users_id_seq;
DROP TABLE IF EXISTS public.admin_users;
DROP SEQUENCE IF EXISTS public.admin_actions_id_seq;
DROP TABLE IF EXISTS public.admin_actions;
DROP FUNCTION IF EXISTS public.update_updated_at_column();
DROP FUNCTION IF EXISTS public.update_deposit_payments_updated_at();
--
-- Name: update_deposit_payments_updated_at(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.update_deposit_payments_updated_at() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_deposit_payments_updated_at() OWNER TO postgres;

--
-- Name: update_updated_at_column(); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;


ALTER FUNCTION public.update_updated_at_column() OWNER TO postgres;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: admin_actions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.admin_actions (
    id integer NOT NULL,
    admin_id integer NOT NULL,
    action_type character varying(50) NOT NULL,
    target_table character varying(50),
    target_id integer,
    action_description text,
    payload jsonb,
    ip_address character varying(45),
    user_agent text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.admin_actions OWNER TO postgres;

--
-- Name: admin_actions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.admin_actions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.admin_actions_id_seq OWNER TO postgres;

--
-- Name: admin_actions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.admin_actions_id_seq OWNED BY public.admin_actions.id;


--
-- Name: admin_users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.admin_users (
    id integer NOT NULL,
    username character varying(100) NOT NULL,
    email character varying(255) NOT NULL,
    password_hash character varying(255) NOT NULL,
    full_name character varying(200),
    role character varying(20) DEFAULT 'admin'::character varying,
    status character varying(20) DEFAULT 'active'::character varying,
    last_login_at timestamp without time zone,
    last_login_ip character varying(45),
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT admin_users_role_check CHECK (((role)::text = ANY ((ARRAY['super_admin'::character varying, 'admin'::character varying, 'viewer'::character varying])::text[]))),
    CONSTRAINT admin_users_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'suspended'::character varying, 'deactivated'::character varying])::text[])))
);


ALTER TABLE public.admin_users OWNER TO postgres;

--
-- Name: admin_users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.admin_users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.admin_users_id_seq OWNER TO postgres;

--
-- Name: admin_users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.admin_users_id_seq OWNED BY public.admin_users.id;


--
-- Name: broadcast_logs; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.broadcast_logs (
    id integer NOT NULL,
    broadcast_id integer,
    event_type character varying(50) NOT NULL,
    status character varying(20) NOT NULL,
    telegram_message_id bigint,
    response_data jsonb,
    error_message text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.broadcast_logs OWNER TO postgres;

--
-- Name: broadcast_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.broadcast_logs_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.broadcast_logs_id_seq OWNER TO postgres;

--
-- Name: broadcast_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.broadcast_logs_id_seq OWNED BY public.broadcast_logs.id;


--
-- Name: broadcasts; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.broadcasts (
    id integer NOT NULL,
    title character varying(255) NOT NULL,
    content_type character varying(20) NOT NULL,
    content_text text,
    media_url text,
    media_caption text,
    poll_question text,
    poll_options jsonb,
    poll_type character varying(20),
    poll_correct_option integer,
    buttons jsonb,
    send_to_telegram boolean DEFAULT false,
    send_to_inapp boolean DEFAULT true,
    telegram_channel_id character varying(100),
    scheduled_for timestamp without time zone,
    send_now boolean DEFAULT false,
    pin_message boolean DEFAULT false,
    is_giveaway boolean DEFAULT false,
    giveaway_winners_count integer DEFAULT 0,
    giveaway_ends_at timestamp without time zone,
    status character varying(20) DEFAULT 'draft'::character varying,
    telegram_message_id bigint,
    sent_at timestamp without time zone,
    error_message text,
    created_by integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT broadcasts_content_type_check CHECK (((content_type)::text = ANY ((ARRAY['text'::character varying, 'photo'::character varying, 'video'::character varying, 'poll'::character varying])::text[]))),
    CONSTRAINT broadcasts_poll_type_check CHECK (((poll_type)::text = ANY ((ARRAY['regular'::character varying, 'quiz'::character varying])::text[]))),
    CONSTRAINT broadcasts_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'scheduled'::character varying, 'sent'::character varying, 'failed'::character varying])::text[])))
);


ALTER TABLE public.broadcasts OWNER TO postgres;

--
-- Name: broadcasts_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.broadcasts_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.broadcasts_id_seq OWNER TO postgres;

--
-- Name: broadcasts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.broadcasts_id_seq OWNED BY public.broadcasts.id;


--
-- Name: card_transactions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.card_transactions (
    id integer NOT NULL,
    card_id integer NOT NULL,
    user_id integer NOT NULL,
    transaction_type character varying(30) NOT NULL,
    amount_usd numeric(15,2) NOT NULL,
    merchant_name character varying(255),
    merchant_category character varying(100),
    description text,
    strow_transaction_id character varying(255),
    wallet_transaction_id integer,
    status character varying(20) DEFAULT 'completed'::character varying,
    metadata jsonb,
    transaction_date timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT card_transactions_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'completed'::character varying, 'declined'::character varying, 'reversed'::character varying])::text[]))),
    CONSTRAINT card_transactions_transaction_type_check CHECK (((transaction_type)::text = ANY ((ARRAY['topup'::character varying, 'topup_fee'::character varying, 'purchase'::character varying, 'refund'::character varying, 'fee'::character varying, 'reversal'::character varying])::text[])))
);


ALTER TABLE public.card_transactions OWNER TO postgres;

--
-- Name: card_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.card_transactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.card_transactions_id_seq OWNER TO postgres;

--
-- Name: card_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.card_transactions_id_seq OWNED BY public.card_transactions.id;


--
-- Name: cards; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.cards (
    id integer NOT NULL,
    user_id integer NOT NULL,
    strow_card_id character varying(255) NOT NULL,
    card_brand character varying(20),
    last4 character varying(4),
    name_on_card character varying(100),
    card_type character varying(20),
    balance_usd numeric(15,2) DEFAULT 0.00,
    status character varying(20) DEFAULT 'active'::character varying,
    frozen_at timestamp without time zone,
    frozen_by integer,
    frozen_reason text,
    unfrozen_at timestamp without time zone,
    creation_fee_usd numeric(10,2),
    creation_wallet_transaction_id integer,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT cards_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'frozen'::character varying, 'closed'::character varying, 'expired'::character varying])::text[])))
);


ALTER TABLE public.cards OWNER TO postgres;

--
-- Name: cards_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.cards_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.cards_id_seq OWNER TO postgres;

--
-- Name: cards_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.cards_id_seq OWNED BY public.cards.id;


--
-- Name: deposit_payments; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.deposit_payments (
    id integer NOT NULL,
    user_id integer NOT NULL,
    telegram_id bigint NOT NULL,
    amount_usd numeric(14,2) NOT NULL,
    amount_etb numeric(14,2) NOT NULL,
    exchange_rate numeric(10,4) DEFAULT 135.00 NOT NULL,
    deposit_fee_etb numeric(14,2) DEFAULT 0 NOT NULL,
    total_etb numeric(14,2) NOT NULL,
    payment_method character varying(50) NOT NULL,
    payment_phone character varying(50),
    payment_account_name character varying(255),
    screenshot_file_id character varying(255),
    screenshot_url text,
    transaction_number character varying(255),
    validation_status character varying(50) DEFAULT 'pending'::character varying,
    validation_response text,
    verification_attempts integer DEFAULT 0,
    verified_at timestamp without time zone,
    verified_by character varying(100),
    rejected_reason text,
    strowallet_deposit_id character varying(100),
    status character varying(50) DEFAULT 'pending'::character varying,
    notes text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    completed_at timestamp without time zone,
    CONSTRAINT deposit_payments_payment_method_check CHECK (((payment_method)::text = ANY ((ARRAY['telebirr'::character varying, 'm-pesa'::character varying, 'cbe'::character varying, 'bank_transfer'::character varying])::text[]))),
    CONSTRAINT deposit_payments_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'screenshot_submitted'::character varying, 'transaction_submitted'::character varying, 'verified'::character varying, 'processing'::character varying, 'completed'::character varying, 'rejected'::character varying, 'cancelled'::character varying])::text[]))),
    CONSTRAINT deposit_payments_validation_status_check CHECK (((validation_status)::text = ANY ((ARRAY['pending'::character varying, 'validating'::character varying, 'verified'::character varying, 'rejected'::character varying, 'manual_approved'::character varying])::text[])))
);


ALTER TABLE public.deposit_payments OWNER TO postgres;

--
-- Name: deposit_payments_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.deposit_payments_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.deposit_payments_id_seq OWNER TO postgres;

--
-- Name: deposit_payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.deposit_payments_id_seq OWNED BY public.deposit_payments.id;


--
-- Name: deposits; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.deposits (
    id integer NOT NULL,
    wallet_id integer NOT NULL,
    user_id integer NOT NULL,
    usd_amount numeric(15,2) NOT NULL,
    etb_amount_quote numeric(15,2) NOT NULL,
    exchange_rate numeric(10,4) NOT NULL,
    fee_percentage numeric(5,2) DEFAULT 0.00,
    fee_flat numeric(10,2) DEFAULT 0.00,
    total_etb_to_pay numeric(15,2) NOT NULL,
    payment_proof_url text,
    payment_reference character varying(255),
    status character varying(20) DEFAULT 'pending'::character varying,
    admin_notes text,
    approved_by integer,
    approved_at timestamp without time zone,
    rejected_by integer,
    rejected_at timestamp without time zone,
    rejection_reason text,
    strow_reference character varying(255),
    wallet_transaction_id integer,
    expires_at timestamp without time zone,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT deposits_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'payment_submitted'::character varying, 'approved'::character varying, 'rejected'::character varying, 'expired'::character varying])::text[])))
);


ALTER TABLE public.deposits OWNER TO postgres;

--
-- Name: deposits_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.deposits_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.deposits_id_seq OWNER TO postgres;

--
-- Name: deposits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.deposits_id_seq OWNED BY public.deposits.id;


--
-- Name: giveaway_entries; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.giveaway_entries (
    id integer NOT NULL,
    broadcast_id integer,
    user_id integer,
    telegram_user_id bigint NOT NULL,
    button_data character varying(255),
    is_winner boolean DEFAULT false,
    entered_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);


ALTER TABLE public.giveaway_entries OWNER TO postgres;

--
-- Name: giveaway_entries_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.giveaway_entries_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.giveaway_entries_id_seq OWNER TO postgres;

--
-- Name: giveaway_entries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.giveaway_entries_id_seq OWNED BY public.giveaway_entries.id;


--
-- Name: settings; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.settings (
    key character varying(100) NOT NULL,
    value jsonb NOT NULL,
    description text,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_by integer
);


ALTER TABLE public.settings OWNER TO postgres;

--
-- Name: user_registrations; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.user_registrations (
    id integer NOT NULL,
    telegram_user_id bigint NOT NULL,
    registration_state character varying(50) DEFAULT 'idle'::character varying,
    is_registered boolean DEFAULT false,
    first_name character varying(100),
    last_name character varying(100),
    date_of_birth character varying(20),
    phone character varying(20),
    email character varying(255),
    address_line1 character varying(255),
    address_city character varying(100),
    address_state character varying(100),
    address_zip character varying(20),
    address_country character varying(2) DEFAULT 'ET'::character varying,
    house_number character varying(20),
    id_type character varying(20),
    id_number character varying(50),
    id_front_photo_url text,
    id_back_photo_url text,
    selfie_photo_url text,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    completed_at timestamp without time zone,
    kyc_status character varying(20) DEFAULT 'pending'::character varying,
    strowallet_customer_id character varying(100)
);


ALTER TABLE public.user_registrations OWNER TO postgres;

--
-- Name: COLUMN user_registrations.kyc_status; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.user_registrations.kyc_status IS 'KYC verification status: pending, approved, rejected';


--
-- Name: COLUMN user_registrations.strowallet_customer_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.user_registrations.strowallet_customer_id IS 'Customer ID from StroWallet API';


--
-- Name: user_registrations_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.user_registrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.user_registrations_id_seq OWNER TO postgres;

--
-- Name: user_registrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.user_registrations_id_seq OWNED BY public.user_registrations.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.users (
    id integer NOT NULL,
    telegram_id bigint,
    email character varying(255) NOT NULL,
    phone character varying(20) NOT NULL,
    first_name character varying(100) NOT NULL,
    last_name character varying(100) NOT NULL,
    kyc_status character varying(20) DEFAULT 'pending'::character varying,
    kyc_submitted_at timestamp without time zone,
    kyc_approved_at timestamp without time zone,
    kyc_rejected_at timestamp without time zone,
    kyc_rejection_reason text,
    strow_customer_id character varying(255),
    id_type character varying(20),
    id_number character varying(50),
    id_image_url text,
    user_photo_url text,
    address_line1 character varying(255),
    address_city character varying(100),
    address_state character varying(100),
    address_zip character varying(20),
    address_country character varying(2) DEFAULT 'ET'::character varying,
    house_number character varying(20),
    date_of_birth date,
    status character varying(20) DEFAULT 'active'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT users_kyc_status_check CHECK (((kyc_status)::text = ANY ((ARRAY['pending'::character varying, 'approved'::character varying, 'rejected'::character varying])::text[]))),
    CONSTRAINT users_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'suspended'::character varying, 'banned'::character varying])::text[])))
);


ALTER TABLE public.users OWNER TO postgres;

--
-- Name: COLUMN users.telegram_id; Type: COMMENT; Schema: public; Owner: postgres
--

COMMENT ON COLUMN public.users.telegram_id IS 'Telegram user ID (NULL for StroWallet-only customers)';


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.users_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.users_id_seq OWNER TO postgres;

--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: wallet_transactions; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.wallet_transactions (
    id integer NOT NULL,
    wallet_id integer NOT NULL,
    user_id integer NOT NULL,
    transaction_type character varying(30) NOT NULL,
    amount_usd numeric(15,2) NOT NULL,
    amount_etb numeric(15,2),
    balance_before_usd numeric(15,2),
    balance_after_usd numeric(15,2),
    reference character varying(255),
    description text,
    status character varying(20) DEFAULT 'completed'::character varying,
    metadata jsonb,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT wallet_transactions_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'completed'::character varying, 'failed'::character varying, 'reversed'::character varying])::text[]))),
    CONSTRAINT wallet_transactions_transaction_type_check CHECK (((transaction_type)::text = ANY ((ARRAY['deposit'::character varying, 'topup'::character varying, 'card_creation_fee'::character varying, 'card_topup_fee'::character varying, 'refund'::character varying, 'admin_adjustment'::character varying])::text[])))
);


ALTER TABLE public.wallet_transactions OWNER TO postgres;

--
-- Name: wallet_transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.wallet_transactions_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.wallet_transactions_id_seq OWNER TO postgres;

--
-- Name: wallet_transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.wallet_transactions_id_seq OWNED BY public.wallet_transactions.id;


--
-- Name: wallets; Type: TABLE; Schema: public; Owner: postgres
--

CREATE TABLE public.wallets (
    id integer NOT NULL,
    user_id integer NOT NULL,
    balance_usd numeric(15,2) DEFAULT 0.00,
    balance_etb numeric(15,2) DEFAULT 0.00,
    status character varying(20) DEFAULT 'active'::character varying,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT wallets_balance_etb_check CHECK ((balance_etb >= (0)::numeric)),
    CONSTRAINT wallets_balance_usd_check CHECK ((balance_usd >= (0)::numeric)),
    CONSTRAINT wallets_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'frozen'::character varying, 'closed'::character varying])::text[])))
);


ALTER TABLE public.wallets OWNER TO postgres;

--
-- Name: wallets_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE public.wallets_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER SEQUENCE public.wallets_id_seq OWNER TO postgres;

--
-- Name: wallets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE public.wallets_id_seq OWNED BY public.wallets.id;


--
-- Name: admin_actions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_actions ALTER COLUMN id SET DEFAULT nextval('public.admin_actions_id_seq'::regclass);


--
-- Name: admin_users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_users ALTER COLUMN id SET DEFAULT nextval('public.admin_users_id_seq'::regclass);


--
-- Name: broadcast_logs id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.broadcast_logs ALTER COLUMN id SET DEFAULT nextval('public.broadcast_logs_id_seq'::regclass);


--
-- Name: broadcasts id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.broadcasts ALTER COLUMN id SET DEFAULT nextval('public.broadcasts_id_seq'::regclass);


--
-- Name: card_transactions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.card_transactions ALTER COLUMN id SET DEFAULT nextval('public.card_transactions_id_seq'::regclass);


--
-- Name: cards id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cards ALTER COLUMN id SET DEFAULT nextval('public.cards_id_seq'::regclass);


--
-- Name: deposit_payments id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposit_payments ALTER COLUMN id SET DEFAULT nextval('public.deposit_payments_id_seq'::regclass);


--
-- Name: deposits id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposits ALTER COLUMN id SET DEFAULT nextval('public.deposits_id_seq'::regclass);


--
-- Name: giveaway_entries id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.giveaway_entries ALTER COLUMN id SET DEFAULT nextval('public.giveaway_entries_id_seq'::regclass);


--
-- Name: user_registrations id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_registrations ALTER COLUMN id SET DEFAULT nextval('public.user_registrations_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: wallet_transactions id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallet_transactions ALTER COLUMN id SET DEFAULT nextval('public.wallet_transactions_id_seq'::regclass);


--
-- Name: wallets id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallets ALTER COLUMN id SET DEFAULT nextval('public.wallets_id_seq'::regclass);


--
-- Data for Name: admin_actions; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- Data for Name: admin_users; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO public.admin_users VALUES (1, 'admin', 'admin@cardbot.local', '$2y$10$44B3Z2K3NJ9jU7pm7jp0ee3QX89F3yPD1r2wwPASeGAQPfmNFXRwG', 'System Administrator', 'super_admin', 'active', '2025-10-30 06:41:14.009067', '172.31.91.34', '2025-10-30 06:34:08.794698', '2025-10-30 06:41:14.009067');


--
-- Data for Name: broadcast_logs; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- Data for Name: broadcasts; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- Data for Name: card_transactions; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- Data for Name: cards; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- Data for Name: deposit_payments; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- Data for Name: deposits; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- Data for Name: giveaway_entries; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- Data for Name: settings; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO public.settings VALUES ('exchange_rate_usd_to_etb', '{"rate": 130.50, "last_updated": "2025-10-20"}', 'USD to ETB exchange rate', '2025-10-30 06:34:08.794698', NULL);
INSERT INTO public.settings VALUES ('card_creation_fee', '{"flat": 1.99, "currency": "USD", "percentage": 1.99}', 'Card creation fee structure', '2025-10-30 06:34:08.794698', NULL);
INSERT INTO public.settings VALUES ('card_topup_fee', '{"flat": 1.99, "currency": "USD", "percentage": 1.99}', 'Card top-up fee structure', '2025-10-30 06:34:08.794698', NULL);
INSERT INTO public.settings VALUES ('deposit_fee', '{"flat": 0.00, "currency": "ETB", "percentage": 0.00}', 'Deposit fee structure', '2025-10-30 06:34:08.794698', NULL);
INSERT INTO public.settings VALUES ('card_limits', '{"max_topup": 10000, "min_topup": 5, "daily_limit": 1000}', 'Card transaction limits', '2025-10-30 06:34:08.794698', NULL);
INSERT INTO public.settings VALUES ('kyc_requirements', '{"require_photo": true, "require_address": true, "require_id_image": true}', 'KYC verification requirements', '2025-10-30 06:34:08.794698', NULL);
INSERT INTO public.settings VALUES ('system_status', '{"allow_deposits": true, "maintenance_mode": false, "allow_card_creation": true}', 'System operational status', '2025-10-30 06:34:08.794698', NULL);


--
-- Data for Name: user_registrations; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: postgres
--

INSERT INTO public.users VALUES (1, 383870190, 'amanuail071@gmail.com', '', 'Kalkidan', 'Semeneh', 'approved', NULL, '2025-10-30 06:39:06.584041', NULL, NULL, 'e0b1c7d8-3948-481a-9f9a-c78954482d2d', NULL, NULL, '/uploads/kyc_documents/383870190_id_image_1761633531_c58f5c7e6dda84f6.jpg', '/uploads/kyc_documents/383870190_user_photo_1761633556_3968603ed2337023.jpg', 'Gulale', NULL, NULL, NULL, 'ET', NULL, '2002-11-16', 'active', '2025-10-30 06:39:06.584041', '2025-10-30 06:39:23.819769');


--
-- Data for Name: wallet_transactions; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- Data for Name: wallets; Type: TABLE DATA; Schema: public; Owner: postgres
--



--
-- Name: admin_actions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.admin_actions_id_seq', 1, false);


--
-- Name: admin_users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.admin_users_id_seq', 1, true);


--
-- Name: broadcast_logs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.broadcast_logs_id_seq', 1, false);


--
-- Name: broadcasts_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.broadcasts_id_seq', 1, false);


--
-- Name: card_transactions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.card_transactions_id_seq', 1, false);


--
-- Name: cards_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.cards_id_seq', 1, false);


--
-- Name: deposit_payments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.deposit_payments_id_seq', 1, false);


--
-- Name: deposits_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.deposits_id_seq', 1, false);


--
-- Name: giveaway_entries_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.giveaway_entries_id_seq', 1, false);


--
-- Name: user_registrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.user_registrations_id_seq', 1, false);


--
-- Name: users_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.users_id_seq', 1, true);


--
-- Name: wallet_transactions_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.wallet_transactions_id_seq', 1, false);


--
-- Name: wallets_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('public.wallets_id_seq', 1, false);


--
-- Name: admin_actions admin_actions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_actions
    ADD CONSTRAINT admin_actions_pkey PRIMARY KEY (id);


--
-- Name: admin_users admin_users_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_users
    ADD CONSTRAINT admin_users_email_key UNIQUE (email);


--
-- Name: admin_users admin_users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_users
    ADD CONSTRAINT admin_users_pkey PRIMARY KEY (id);


--
-- Name: admin_users admin_users_username_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_users
    ADD CONSTRAINT admin_users_username_key UNIQUE (username);


--
-- Name: broadcast_logs broadcast_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.broadcast_logs
    ADD CONSTRAINT broadcast_logs_pkey PRIMARY KEY (id);


--
-- Name: broadcasts broadcasts_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.broadcasts
    ADD CONSTRAINT broadcasts_pkey PRIMARY KEY (id);


--
-- Name: card_transactions card_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.card_transactions
    ADD CONSTRAINT card_transactions_pkey PRIMARY KEY (id);


--
-- Name: cards cards_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cards
    ADD CONSTRAINT cards_pkey PRIMARY KEY (id);


--
-- Name: cards cards_strow_card_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cards
    ADD CONSTRAINT cards_strow_card_id_key UNIQUE (strow_card_id);


--
-- Name: deposit_payments deposit_payments_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposit_payments
    ADD CONSTRAINT deposit_payments_pkey PRIMARY KEY (id);


--
-- Name: deposits deposits_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT deposits_pkey PRIMARY KEY (id);


--
-- Name: giveaway_entries giveaway_entries_broadcast_id_telegram_user_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.giveaway_entries
    ADD CONSTRAINT giveaway_entries_broadcast_id_telegram_user_id_key UNIQUE (broadcast_id, telegram_user_id);


--
-- Name: giveaway_entries giveaway_entries_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.giveaway_entries
    ADD CONSTRAINT giveaway_entries_pkey PRIMARY KEY (id);


--
-- Name: settings settings_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_pkey PRIMARY KEY (key);


--
-- Name: user_registrations user_registrations_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_registrations
    ADD CONSTRAINT user_registrations_pkey PRIMARY KEY (id);


--
-- Name: user_registrations user_registrations_telegram_user_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.user_registrations
    ADD CONSTRAINT user_registrations_telegram_user_id_key UNIQUE (telegram_user_id);


--
-- Name: users users_email_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_key UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: users users_telegram_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_telegram_id_key UNIQUE (telegram_id);


--
-- Name: wallet_transactions wallet_transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallet_transactions
    ADD CONSTRAINT wallet_transactions_pkey PRIMARY KEY (id);


--
-- Name: wallets wallets_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallets
    ADD CONSTRAINT wallets_pkey PRIMARY KEY (id);


--
-- Name: wallets wallets_user_id_key; Type: CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallets
    ADD CONSTRAINT wallets_user_id_key UNIQUE (user_id);


--
-- Name: idx_admin_actions_admin_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_admin_actions_admin_id ON public.admin_actions USING btree (admin_id);


--
-- Name: idx_admin_actions_created_at; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_admin_actions_created_at ON public.admin_actions USING btree (created_at);


--
-- Name: idx_admin_actions_type; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_admin_actions_type ON public.admin_actions USING btree (action_type);


--
-- Name: idx_admin_users_email; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_admin_users_email ON public.admin_users USING btree (email);


--
-- Name: idx_admin_users_username; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_admin_users_username ON public.admin_users USING btree (username);


--
-- Name: idx_broadcast_logs_broadcast; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_broadcast_logs_broadcast ON public.broadcast_logs USING btree (broadcast_id);


--
-- Name: idx_broadcasts_scheduled; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_broadcasts_scheduled ON public.broadcasts USING btree (scheduled_for) WHERE ((status)::text = 'scheduled'::text);


--
-- Name: idx_broadcasts_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_broadcasts_status ON public.broadcasts USING btree (status);


--
-- Name: idx_card_transactions_card_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_card_transactions_card_id ON public.card_transactions USING btree (card_id);


--
-- Name: idx_card_transactions_date; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_card_transactions_date ON public.card_transactions USING btree (transaction_date);


--
-- Name: idx_card_transactions_type; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_card_transactions_type ON public.card_transactions USING btree (transaction_type);


--
-- Name: idx_card_transactions_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_card_transactions_user_id ON public.card_transactions USING btree (user_id);


--
-- Name: idx_cards_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cards_status ON public.cards USING btree (status);


--
-- Name: idx_cards_strow_card_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cards_strow_card_id ON public.cards USING btree (strow_card_id);


--
-- Name: idx_cards_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_cards_user_id ON public.cards USING btree (user_id);


--
-- Name: idx_deposit_payments_created_at; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_deposit_payments_created_at ON public.deposit_payments USING btree (created_at);


--
-- Name: idx_deposit_payments_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_deposit_payments_status ON public.deposit_payments USING btree (status);


--
-- Name: idx_deposit_payments_telegram_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_deposit_payments_telegram_id ON public.deposit_payments USING btree (telegram_id);


--
-- Name: idx_deposit_payments_transaction_number; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_deposit_payments_transaction_number ON public.deposit_payments USING btree (transaction_number);


--
-- Name: idx_deposit_payments_validation_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_deposit_payments_validation_status ON public.deposit_payments USING btree (validation_status);


--
-- Name: idx_deposits_created_at; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_deposits_created_at ON public.deposits USING btree (created_at);


--
-- Name: idx_deposits_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_deposits_status ON public.deposits USING btree (status);


--
-- Name: idx_deposits_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_deposits_user_id ON public.deposits USING btree (user_id);


--
-- Name: idx_deposits_wallet_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_deposits_wallet_id ON public.deposits USING btree (wallet_id);


--
-- Name: idx_giveaway_entries_broadcast; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_giveaway_entries_broadcast ON public.giveaway_entries USING btree (broadcast_id);


--
-- Name: idx_giveaway_entries_user; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_giveaway_entries_user ON public.giveaway_entries USING btree (user_id);


--
-- Name: idx_user_registrations_is_registered; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_registrations_is_registered ON public.user_registrations USING btree (is_registered);


--
-- Name: idx_user_registrations_kyc_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_registrations_kyc_status ON public.user_registrations USING btree (kyc_status);


--
-- Name: idx_user_registrations_state; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_registrations_state ON public.user_registrations USING btree (registration_state);


--
-- Name: idx_user_registrations_strowallet_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_registrations_strowallet_id ON public.user_registrations USING btree (strowallet_customer_id);


--
-- Name: idx_user_registrations_telegram_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_user_registrations_telegram_id ON public.user_registrations USING btree (telegram_user_id);


--
-- Name: idx_users_email; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_email ON public.users USING btree (email);


--
-- Name: idx_users_kyc_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_users_kyc_status ON public.users USING btree (kyc_status);


--
-- Name: idx_users_telegram_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE UNIQUE INDEX idx_users_telegram_id ON public.users USING btree (telegram_id) WHERE (telegram_id IS NOT NULL);


--
-- Name: idx_wallet_transactions_status; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_wallet_transactions_status ON public.wallet_transactions USING btree (status);


--
-- Name: idx_wallet_transactions_type; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_wallet_transactions_type ON public.wallet_transactions USING btree (transaction_type);


--
-- Name: idx_wallet_transactions_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_wallet_transactions_user_id ON public.wallet_transactions USING btree (user_id);


--
-- Name: idx_wallet_transactions_wallet_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_wallet_transactions_wallet_id ON public.wallet_transactions USING btree (wallet_id);


--
-- Name: idx_wallets_user_id; Type: INDEX; Schema: public; Owner: postgres
--

CREATE INDEX idx_wallets_user_id ON public.wallets USING btree (user_id);


--
-- Name: deposit_payments trigger_deposit_payments_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER trigger_deposit_payments_updated_at BEFORE UPDATE ON public.deposit_payments FOR EACH ROW EXECUTE FUNCTION public.update_deposit_payments_updated_at();


--
-- Name: admin_users update_admin_users_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_admin_users_updated_at BEFORE UPDATE ON public.admin_users FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: cards update_cards_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_cards_updated_at BEFORE UPDATE ON public.cards FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: deposits update_deposits_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_deposits_updated_at BEFORE UPDATE ON public.deposits FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: users update_users_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON public.users FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: wallet_transactions update_wallet_transactions_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_wallet_transactions_updated_at BEFORE UPDATE ON public.wallet_transactions FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: wallets update_wallets_updated_at; Type: TRIGGER; Schema: public; Owner: postgres
--

CREATE TRIGGER update_wallets_updated_at BEFORE UPDATE ON public.wallets FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();


--
-- Name: admin_actions admin_actions_admin_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.admin_actions
    ADD CONSTRAINT admin_actions_admin_id_fkey FOREIGN KEY (admin_id) REFERENCES public.admin_users(id);


--
-- Name: broadcast_logs broadcast_logs_broadcast_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.broadcast_logs
    ADD CONSTRAINT broadcast_logs_broadcast_id_fkey FOREIGN KEY (broadcast_id) REFERENCES public.broadcasts(id) ON DELETE CASCADE;


--
-- Name: broadcasts broadcasts_created_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.broadcasts
    ADD CONSTRAINT broadcasts_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.admin_users(id) ON DELETE SET NULL;


--
-- Name: card_transactions card_transactions_card_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.card_transactions
    ADD CONSTRAINT card_transactions_card_id_fkey FOREIGN KEY (card_id) REFERENCES public.cards(id) ON DELETE CASCADE;


--
-- Name: card_transactions card_transactions_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.card_transactions
    ADD CONSTRAINT card_transactions_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: card_transactions card_transactions_wallet_transaction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.card_transactions
    ADD CONSTRAINT card_transactions_wallet_transaction_id_fkey FOREIGN KEY (wallet_transaction_id) REFERENCES public.wallet_transactions(id);


--
-- Name: cards cards_creation_wallet_transaction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cards
    ADD CONSTRAINT cards_creation_wallet_transaction_id_fkey FOREIGN KEY (creation_wallet_transaction_id) REFERENCES public.wallet_transactions(id);


--
-- Name: cards cards_frozen_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cards
    ADD CONSTRAINT cards_frozen_by_fkey FOREIGN KEY (frozen_by) REFERENCES public.users(id);


--
-- Name: cards cards_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.cards
    ADD CONSTRAINT cards_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: deposits deposits_approved_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT deposits_approved_by_fkey FOREIGN KEY (approved_by) REFERENCES public.admin_users(id);


--
-- Name: deposits deposits_rejected_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT deposits_rejected_by_fkey FOREIGN KEY (rejected_by) REFERENCES public.admin_users(id);


--
-- Name: deposits deposits_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT deposits_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: deposits deposits_wallet_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT deposits_wallet_id_fkey FOREIGN KEY (wallet_id) REFERENCES public.wallets(id) ON DELETE CASCADE;


--
-- Name: deposits deposits_wallet_transaction_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposits
    ADD CONSTRAINT deposits_wallet_transaction_id_fkey FOREIGN KEY (wallet_transaction_id) REFERENCES public.wallet_transactions(id);


--
-- Name: deposit_payments fk_deposit_payments_user; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.deposit_payments
    ADD CONSTRAINT fk_deposit_payments_user FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: giveaway_entries giveaway_entries_broadcast_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.giveaway_entries
    ADD CONSTRAINT giveaway_entries_broadcast_id_fkey FOREIGN KEY (broadcast_id) REFERENCES public.broadcasts(id) ON DELETE CASCADE;


--
-- Name: giveaway_entries giveaway_entries_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.giveaway_entries
    ADD CONSTRAINT giveaway_entries_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: settings settings_updated_by_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.settings
    ADD CONSTRAINT settings_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES public.admin_users(id);


--
-- Name: wallet_transactions wallet_transactions_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallet_transactions
    ADD CONSTRAINT wallet_transactions_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: wallet_transactions wallet_transactions_wallet_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallet_transactions
    ADD CONSTRAINT wallet_transactions_wallet_id_fkey FOREIGN KEY (wallet_id) REFERENCES public.wallets(id) ON DELETE CASCADE;


--
-- Name: wallets wallets_user_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY public.wallets
    ADD CONSTRAINT wallets_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

