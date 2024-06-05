import jwt from "jsonwebtoken";
import bcrypt from "bcryptjs";
import * as errors from "../validation/errors";
import ApiError from "../validation/errors/classes/ApiError";
import User from "../models/User";
import stacktrace from "stack-trace";
import ErrorContext from "../validation/errors/classes/ErrorContext";

// refactor to make these validations be made with express-validator
const store = async (req, res, next) => {
  const fullPath = req.baseUrl + req.path;
  const { nickname, password } = req.body;

  try {
    const user = await User.findOne({ where: { nickname } });

    if (!user)
      throw new ApiError(...errors.controllers.createInvalidCredentials());

    const passwordsMatch = await bcrypt.compare(password, user.password);

    if (!passwordsMatch)
      throw new ApiError(...errors.controllers.createPasswordsNotMatch());

    const { id } = user;
    const token = jwt.sign({ id }, process.env.TOKEN_SECRET, {
      expiresIn: process.env.TOKEN_EXPIRATION,
    });

    res.json({ token });
  } catch (err) {
    const trace = stacktrace.parse(err);
    const errorContext = new ErrorContext(err, trace);

    next(errorContext);
  }
};

export default { store };
