-- auth.lua
local http = require "resty.http"
local lrucache = require "resty.lrucache"
local cjson = require "cjson"

local cache, err = lrucache.new(1000, 60)  -- 1000 записей, TTL 60 сек
if not cache then
    ngx.log(ngx.ERR, "failed to create cache: ", err)
end

-- Конфигурация introspection клиента (лучше вынести в переменные окружения)
local client_id = "019e0a2e-2208-732f-b7aa-96adf97d435a"
local client_secret = "hMeDXAVa9FerbhA1jyGU2zdm5XQ6fLufKc7DDkxi"
local basic_auth = "Basic " .. ngx.encode_base64(client_id .. ":" .. client_secret)

local function get_token()
    local auth_header = ngx.var.http_authorization
    if not auth_header then return nil end
    local _, _, token = string.find(auth_header, "^%s*[Bb]earer%s+(.+)$")
    return token
end

local function verify_token(token)
    local httpc = http.new()
    httpc:set_timeout(2000)
    local url = "http://main-app:8000/api/auth/verify"
    ngx.log(ngx.INFO, "Verifying token at URL: ", url)  -- лог для проверки

    local res, err = httpc:request_uri(url, {
        method = "GET",
        body = "token=" .. ngx.escape_uri(token),
        headers = {
            ["Authorization"] = "Bearer " .. token,
            ["Host"] = "main-app",
        }
    })
    if not res then
        ngx.log(ngx.ERR, "Verify request failed: ", err)
        return nil
    end
    -- Логируем статус и тело ответа
    ngx.log(ngx.INFO, "Verify response status: ", res.status)
    ngx.log(ngx.INFO, "Verify response body: ", res.body or "empty")
    ngx.log(ngx.INFO, "Verify response headers: ", require("cjson").encode(res.headers))
    if res.status == 200 then
        -- Извлекаем user_id из заголовка ответа
        return res.headers["X-User-Id"]
        -- Извлекаем user_id из заголовка ответа
        -- local user_id = res.headers["X-User-Id"]
        -- if user_id then
        --    return user_id
        -- else
        --    ngx.log(ngx.WARN, "No X-User-Id header in response")
        --    return nil
        -- end
    else
        ngx.log(ngx.INFO, "Token invalid, status: ", res.status)
        return nil
    end
end

local token = get_token()
if not token then
    ngx.status = 401
    ngx.header.content_type = "application/json"
    ngx.say('{"error":"Missing Authorization header"}')
    return ngx.exit(401)
end

local user_id = cache:get(token)
if user_id == nil then
    user_id = verify_token(token)
    if user_id then
        cache:set(token, user_id)
    else
        cache:set(token, false)   -- кэшируем и невалидные токены
        ngx.status = 401
        ngx.say('{"error":"Invalid token"}')
        return ngx.exit(401)
    end
elseif user_id == false then
    ngx.status = 401
    ngx.say('{"error":"Invalid token (cached)"}')
    return ngx.exit(401)
end

ngx.req.set_header("X-User-Id", user_id)