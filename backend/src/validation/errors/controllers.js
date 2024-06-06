import { v4 as uuidv4 } from "uuid";
import Base from "./classes/Base";

export const createValidationError = (fields) =>
  new Base(
    "/errors/validation-failed",
    "Fields Validation Failed",
    400,
    "One or more fields contain invalid values.",
    `The fields '${fields}' contain invalid values.`,
    uuidv4()
  );

export const createUnexpectedError = (fileName) => {
  const fileNameMatch =
    fileName.match(/src(.*)/) || fileName.match(/node_modules.*$/);
  return new Base(
    "/errors/server-unexpected-error",
    "Unexpected Error on Server",
    500,
    "An unexpected error occurred on the server. Please try again later.",
    `An unexpected error occurred on ${fileNameMatch[0]} - related to database, external services etc.`,
    uuidv4()
  );
};

export const createRateLimiter = () => {
  return new Base(
    "/errors/rate-limit-reached",
    "Request Rate Limit Reached",
    429,
    "The request rate limit has been reached. Please try again later.",
    `The request rate limit, which is 100 requests in 24 hours, has been reached.`,
    uuidv4()
  );
};

export const createMissingAuthorization = (path) => {
  return new Base(
    "/errors/authorization-failed",
    "Missing Authorization Header",
    401,
    "'authorization' header is required.",
    `'authorization' header is required to access resource from ${path}.`,
    uuidv4()
  );
};

export const createInvalidAuthorizationFormat = (path) => {
  return new Base(
    "/errors/authorization-failed",
    "Invalid Authorization Header Format",
    401,
    "'authorization' header format is invalid.",
    `'authorization' header sent to ${path} is invalid due to incorrect format. Please provide it in "Bearer [Token]" format`,
    uuidv4()
  );
};

export const createInvalidToken = (path) => {
  return new Base(
    "/errors/authorization-failed",
    "Invalid Access Token",
    401,
    "The token is invalid.",
    `The access token provided in headers sent to ${path} has been expired or tampered with.`,
    uuidv4()
  );
};

export const createInvalidTokenDecodedPayload = (path) => {
  return new Base(
    "/errors/authorization-failed",
    "Invalid Access Token Decoded Payload",
    401,
    "The token's decoded payload data is invalid",
    `The access token provided in headers sent to ${path} has invalid data: the 'user' object decoded do not exists anymore.`,
    uuidv4()
  );
};

export const createMissingId = (path) => {
  return new Base(
    "/errors/id-param-missing",
    "Missing Id Parameter",
    400,
    "'id' parameter is required.",
    `'id' parameter is required on ${path}. It's missing`,
    uuidv4()
  );
};

export const createMissingCredentials = (path) => {
  return new Base(
    "/errors/login-credentials",
    "Missing Credentials",
    400,
    "'nickname' and 'password' are required fields.",
    `'nickname' and 'password' are required on ${path}. One or both of them are missing,`,
    uuidv4()
  );
};

export const createInvalidCredentials = () =>
  new Base(
    "/errors/login-credentials",
    "Invalid Credentials",
    401,
    "'nickname' or/and 'password' invalid.",
    `'nickname' or/and 'password' are invalid. Provide valid credentials.`,
    uuidv4()
  );

export const createPasswordsNotMatch = () =>
  new Base(
    "/errors/passwords-not-match",
    "Passwords Not Match",
    401,
    "Invalid password.",
    `An user with the provided nickname exists, but the password is wrong.`,
    uuidv4()
  );

export const createUserNotFound = (id, path) => {
  return new Base(
    "/errors/user-not-found",
    "User Not Found",
    404,
    "No user has been found.",
    `User ${id} has not been found on ${path}.`,
    uuidv4()
  );
};
