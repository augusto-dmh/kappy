import rateLimit from "express-rate-limit";
import ApiError from "../validation/errors/classes/ApiError";
import * as errors from "../validation/errors/controllers";
import ErrorContext from "../validation/errors/classes/ErrorContext";
import stacktrace from "stack-trace";

export default rateLimit({
  windowMs: 1 * 60 * 60 * 1000,
  max: 1000,
  handler: function (req, res, next) {
    try {
      throw new ApiError(...errors.createRateLimiter());
    } catch (err) {
      const trace = stacktrace.parse(err);
      const errorContext = new ErrorContext(err, trace);

      next(errorContext);
    }
  },
  standardHeaders: true,
  legacyHeaders: false,
});
