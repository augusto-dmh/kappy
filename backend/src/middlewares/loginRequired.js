import jwt from "jsonwebtoken";
import User from "../models/User";
import ApiError from "../validation/errors/classes/ApiError";
import * as errors from "../validation/errors/controllers";
import stacktrace from "stack-trace";
import ErrorContext from "../validation/errors/classes/ErrorContext";

export default async (req, res, next) => {
  const fullPath = req.baseUrl + req.path;
  const { authorization } = req.headers;

  try {
    if (!authorization || !authorization.startsWith("Bearer ")) {
      const errorBase = !authorization
        ? errors.createMissingAuthorization(fullPath)
        : errors.createInvalidAuthorizationFormat(fullPath);

      throw new ApiError(...errorBase);
    }

    const [, token] = authorization.split(" ");

    const data = jwt.verify(token, process.env.TOKEN_SECRET, (err, decoded) => {
      if (err) throw new ApiError(...errors.createInvalidToken(fullPath));
      return decoded;
    });
    const { id } = data;

    const user = await User.findByPk(id);

    if (!user)
      throw new ApiError(...errors.createInvalidTokenDecodedPayload(fullPath));

    req.user = user;

    return next();
  } catch (err) {
    const trace = stacktrace.parse(err);
    const errorContext = new ErrorContext(err, trace);

    next(errorContext);
  }
};
