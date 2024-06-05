import { Router } from "express";
import userController from "../controllers/user";
import loginRequired from "../middlewares/loginRequired";
import { body } from "express-validator";
import * as validations from "../validation";
import * as errors from "../validation/errors/models";
import Avatar from "../models/Avatar";
import User from "../models/User";

const router = new Router();

const fieldsValidation = [
  // FOR UPDATE, MAKE AN EQUAL VALIDATION MIDDLEWARES ARRAY BUT WITH ALL FIELDS AS OPTIONAL
  [
    body("nickname")
      .notEmpty()
      .withMessage(errors.nickname.empty)
      .bail()
      .isLength({ min: 0, max: 20 })
      .withMessage(errors.nickname.invalidLength)
      .escape(),
  ],
  [
    body("birthdate")
      .notEmpty()
      .withMessage(errors.birthdate.empty)
      .bail()
      .isDate()
      .withMessage(errors.birthdate.nonDate)
      .escape(),
  ],
  [
    body("isAdmin")
      .optional()
      .isBoolean()
      .withMessage(errors.isAdmin.nonBoolean)
      .bail()
      .escape(),
  ],
  [
    body("selectedAvatar")
      .optional()
      .notEmpty()
      .withMessage(errors.selectedAvatar.empty)
      .bail()
      .escape(),
  ],
  [
    body("password")
      .notEmpty()
      .withMessage(errors.password.empty)
      .bail()
      .isLength({ min: 6, max: 50 })
      .withMessage(errors.password.invalidLength)
      .escape(),
  ],
  [
    body("xp")
      .optional()
      .notEmpty()
      .withMessage(errors.xp.empty)
      .bail()
      .isInt()
      .withMessage(errors.xp.nonInteger)
      .escape(),
  ],
];

router.post("/users", fieldsValidation, userController.store);
router.get("/users", userController.index);
router.get("/users/user", loginRequired, userController.show);
router.put("/users", fieldsValidation, loginRequired, userController.update);
router.delete("/users", loginRequired, userController.destroy);

export default router;
